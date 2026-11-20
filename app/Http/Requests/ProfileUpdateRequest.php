<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'graduation_year' => ['nullable', 'integer', 'min:1929', 'max:'.(now()->year + 7)],
            'current_class' => ['nullable', 'string', 'max:40'],
            'admission_year' => ['nullable', 'integer', 'min:1929', 'max:'.now()->year],
            'house' => ['nullable', Rule::in(\App\Support\Roles::HOUSES)],
            'show_in_directory' => ['boolean'],
            'digest_opt_in' => ['boolean'],
            'notify_email' => ['boolean'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
