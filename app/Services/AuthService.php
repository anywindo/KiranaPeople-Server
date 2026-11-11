<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService extends BaseService
{
    public function __construct(UserRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Register a new user and return a token.
     */
    public function register(array $data): array
    {
        $user = $this->transaction(function () use ($data) {
            $user = $this->repository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],  // cast hashes it
                'phone' => $data['phone'] ?? null,
            ]);

            // Assign default role
            $user->assignRole('user');

            return $user;
        });

        $expiresAt = $this->tokenExpiresAt();
        $token = $user->createToken('auth-token', ['read', 'write'], $expiresAt)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('sanctum.expiration', config('api.token_expiry', 1440)),
            'expires_at' => $expiresAt?->toISOString(),
        ];
    }

    /**
     * Login and return token.
     */
    public function login(array $credentials): array
    {
        $login = $credentials['login']
            ?? $credentials['email']
            ?? $credentials['employee_id']
            ?? $credentials['username']
            ?? null;

        /** @var User|null $user */
        $user = User::findByLogin($login);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been disabled.'],
            ]);
        }

        // Revoke all previous tokens on login (single-session by default)
        $user->tokens()->delete();

        // Grant token abilities based on role
        $abilities = $this->abilitiesForUser($user);
        $expiresAt = $this->tokenExpiresAt();
        $token = $user->createToken('auth-token', $abilities, $expiresAt)->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('sanctum.expiration', config('api.token_expiry', 1440)),
            'expires_at' => $expiresAt?->toISOString(),
        ];
    }

    /**
     * Logout: revoke current token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Refresh: revoke current token, issue a new one.
     */
    public function refresh(User $user): array
    {
        $user->currentAccessToken()->delete();
        $abilities = $this->abilitiesForUser($user);
        $expiresAt = $this->tokenExpiresAt();
        $token = $user->createToken('auth-token', $abilities, $expiresAt)->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('sanctum.expiration', config('api.token_expiry', 1440)),
            'expires_at' => $expiresAt?->toISOString(),
        ];
    }

    /**
     * Calculate token expiration datetime.
     */
    private function tokenExpiresAt(): ?\DateTimeInterface
    {
        $minutes = config('sanctum.expiration', config('api.token_expiry', 1440));

        return $minutes ? now()->addMinutes((int) $minutes) : null;
    }

    /**
     * Map roles → token abilities.
     */
    private function abilitiesForUser(User $user): array
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return ['read', 'write', 'delete'];
        }

        if ($user->hasRole('manager')) {
            return ['read', 'write'];
        }

        return ['read'];
    }
}
