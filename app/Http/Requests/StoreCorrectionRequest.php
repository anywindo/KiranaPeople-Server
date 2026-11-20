<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCorrectionRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'attendance_id' => [
                'required',
                'integer',
                Rule::exists('attendances', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'reason' => ['required', 'string', 'max:1000'],
            'requested_changes' => ['required', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attendance_id.exists' => 'The selected attendance record is invalid or does not belong to you.',
        ];
    }
}
