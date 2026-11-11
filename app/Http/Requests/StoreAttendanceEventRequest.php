<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'event_type' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\EventType::class)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'selfie' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'], // Max 10MB
            'is_mocked' => ['nullable'],
            'accuracy' => ['nullable', 'numeric'],
            'altitude' => ['nullable', 'numeric'],
            'device_timestamp' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'integrity_status' => ['nullable', 'string', 'max:50'],
            'is_rooted' => ['nullable'],
            'is_hooked' => ['nullable'],
            'is_emulator' => ['nullable'],
            'is_debugger' => ['nullable'],
            'is_developer_mode' => ['nullable'],
            'fake_gps_app_installed' => ['nullable'],
            'integrity_signals' => ['nullable', 'string', 'max:1000'],
            'app_signature' => ['nullable', 'string', 'max:255'],
        ];
    }
}
