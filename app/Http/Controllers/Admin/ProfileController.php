<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function index()
    {
        return view('admin.profile.index', [
            'user' => auth()->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'dob' => 'nullable|date',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                try {
                    Storage::disk('r2')->delete($user->avatar);
                } catch (\Throwable $e) {
                }
                try {
                    Storage::disk('public')->delete($user->avatar);
                } catch (\Throwable $e) {
                }
            }
            $fileUploadService = new \App\Services\FileUploadService;
            $validated['avatar'] = $fileUploadService->uploadImage($request->file('avatar'), 'avatars');
            $validated['avatar_base64'] = null;
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return back()->with('success', 'Profil berhasil diperbarui!');
    }
}
