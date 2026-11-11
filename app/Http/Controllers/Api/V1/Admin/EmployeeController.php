<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = User::role('Employee')->with(['location', 'roles']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->has('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $employees = $query->latest('id')->paginate($perPage);

        return UserResource::collection($employees);
    }

    public function store(Request $request, FileUploadService $fileUploadService)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|string|max:50|unique:users,employee_id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'nik' => 'required|string|unique:users,nik|max:255',
            'dob' => 'required|date',
            'phone' => 'required|string|max:255',
            'location_id' => 'required|exists:locations,id',
            'avatar' => 'nullable|image|max:2048', // 2MB max
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $dob = $validated['dob'] ?? null;
        if (! $dob && ! empty($validated['nik'])) {
            $dob = User::parseDobDateFromNik($validated['nik']);
        }

        $passwordString = ! empty($validated['password'])
            ? $validated['password']
            : User::computeDefaultPassword($dob, $validated['nik'] ?? null);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $fileUploadService->uploadImage($request->file('avatar'), 'avatars');
        }

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nik' => $validated['nik'],
            'dob' => $dob,
            'phone' => $validated['phone'],
            'location_id' => $validated['location_id'],
            'avatar' => $avatarPath,
            'password' => \Illuminate\Support\Facades\Hash::make($passwordString),
        ];

        if (! empty($validated['employee_id'])) {
            $userData['employee_id'] = $validated['employee_id'];
        }

        $user = User::create($userData);

        $user->assignRole('Employee');

        return response()->json([
            'message' => 'Employee created successfully.',
            'data' => $user,
        ], 201);
    }

    public function show(User $employee)
    {
        return response()->json([
            'message' => 'Employee retrieved successfully.',
            'data' => $employee,
        ]);
    }

    public function update(Request $request, User $employee, FileUploadService $fileUploadService)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|string|max:50|unique:users,employee_id,'.$employee->id,
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$employee->id,
            'nik' => 'required|string|max:255|unique:users,nik,'.$employee->id,
            'dob' => 'required|date',
            'phone' => 'required|string|max:255',
            'location_id' => 'required|exists:locations,id',
            'avatar' => 'nullable|image|max:2048',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (array_key_exists('dob', $validated) && empty($validated['dob']) && ! empty($validated['nik'])) {
            $validated['dob'] = User::parseDobDateFromNik($validated['nik']);
        }

        if ($request->hasFile('avatar')) {
            if ($employee->avatar) {
                try {
                    Storage::disk('r2')->delete($employee->avatar);
                } catch (\Throwable $e) {
                }
                try {
                    Storage::disk('public')->delete($employee->avatar);
                } catch (\Throwable $e) {
                }
            }
            $validated['avatar'] = $fileUploadService->uploadImage($request->file('avatar'), 'avatars');
        } else {
            unset($validated['avatar']);
        }

        if (! empty($validated['password'])) {
            $validated['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $employee->update($validated);

        return response()->json([
            'message' => 'Employee updated successfully.',
            'data' => $employee,
        ]);
    }

    public function destroy(User $employee)
    {
        $employee->delete();

        return response()->json([
            'message' => 'Employee deleted successfully.',
        ]);
    }
}
