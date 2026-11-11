<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SanctumTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'Password123!',
            'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $this->user->assignRole('Employee');
    }

    public function test_login_creates_token_with_expiration_attributes(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'employee@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'token',
                'token_type',
                'expires_in',
                'expires_at',
                'user',
            ],
        ]);

        $this->assertEquals(1440, $response->json('data.expires_in'));
        $this->assertNotNull($response->json('data.expires_at'));

        $tokenRecord = PersonalAccessToken::where('tokenable_id', $this->user->id)->first();
        $this->assertNotNull($tokenRecord);
        $this->assertNotNull($tokenRecord->expires_at);
        $this->assertTrue($tokenRecord->expires_at->isFuture());
    }

    public function test_authenticated_request_succeeds_with_valid_token(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'login' => 'employee@example.com',
            'password' => 'Password123!',
        ]);

        $token = $loginResponse->json('data.token');

        $this->app['auth']->forgetGuards();

        $profileResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/profile');

        $profileResponse->assertStatus(200);
        $profileResponse->assertJsonPath('data.email', 'employee@example.com');
    }

    public function test_expired_token_is_rejected_with_401(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'login' => 'employee@example.com',
            'password' => 'Password123!',
        ]);

        $token = $loginResponse->json('data.token');

        $this->app['auth']->forgetGuards();

        // Fast-forward time past expiration (1440 minutes + 10 minutes)
        Carbon::setTestNow(now()->addMinutes(1450));

        $profileResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/profile');

        $profileResponse->assertStatus(401);
        $profileResponse->assertJsonPath('meta.message', 'Unauthenticated');

        Carbon::setTestNow(); // Reset time
    }

    public function test_token_with_past_expires_at_in_database_is_rejected(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'login' => 'employee@example.com',
            'password' => 'Password123!',
        ]);

        $token = $loginResponse->json('data.token');

        // Manually expire the token in database
        PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->update([
                'expires_at' => now()->subMinute(),
                'created_at' => now()->subDay(),
            ]);

        $this->app['auth']->forgetGuards();

        $profileResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/profile');

        $profileResponse->assertStatus(401);
        $profileResponse->assertJsonPath('meta.message', 'Unauthenticated');
    }

    public function test_token_refresh_issues_new_token_and_revokes_old_token(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'login' => 'employee@example.com',
            'password' => 'Password123!',
        ]);

        $oldToken = $loginResponse->json('data.token');

        $this->app['auth']->forgetGuards();

        $refreshResponse = $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->postJson('/api/v1/auth/refresh');

        $refreshResponse->assertStatus(200);
        $refreshResponse->assertJsonStructure([
            'data' => [
                'token',
                'token_type',
                'expires_in',
                'expires_at',
            ],
        ]);

        $newToken = $refreshResponse->json('data.token');
        $this->assertNotEquals($oldToken, $newToken);

        $this->app['auth']->forgetGuards();

        // Old token should be revoked and unusable
        $oldTokenResponse = $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->getJson('/api/v1/auth/profile');
        $oldTokenResponse->assertStatus(401);

        $this->app['auth']->forgetGuards();

        // New token should be valid and active
        $newTokenResponse = $this->withHeader('Authorization', 'Bearer '.$newToken)
            ->getJson('/api/v1/auth/profile');
        $newTokenResponse->assertStatus(200);
    }

    public function test_sanctum_prune_expired_command_deletes_expired_tokens(): void
    {
        // 1. Expired token older than prune threshold (48 hours ago)
        $expiredToken = $this->user->createToken(
            'expired-token',
            ['*'],
            now()->subHours(48)
        );
        PersonalAccessToken::where('id', $expiredToken->accessToken->id)->update([
            'created_at' => now()->subHours(48),
            'expires_at' => now()->subHours(48),
        ]);

        // 2. Freshly active token (expires in 24 hours)
        $activeToken = $this->user->createToken(
            'active-token',
            ['*'],
            now()->addHours(24)
        );

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $expiredToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $activeToken->accessToken->id]);

        // Run prune command with default 24h retention
        Artisan::call('sanctum:prune-expired', ['--hours' => 24]);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $expiredToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $activeToken->accessToken->id]);
    }
}
