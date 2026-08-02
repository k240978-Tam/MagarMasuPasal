<?php

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.manage');
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')
                    ->ignore($userId)
                    ->where('business_id', $this->user()->business_id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('business_id', null)],
            'default_branch_id' => ['required', 'exists:branches,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in('active', 'suspended')],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A user with this email already exists in your business.',
            'role.exists' => 'The selected role is invalid.',
            'default_branch_id.exists' => 'The selected branch is invalid.',
        ];
    }
}
