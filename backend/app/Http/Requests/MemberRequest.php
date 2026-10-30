<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $member = $this->route('member');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'membership_code' => ['nullable', 'string', 'max:32', Rule::unique('users', 'membership_code')->ignore($member?->id)],
            'role' => ['sometimes', Rule::in($this->user()?->role === 'admin' ? ['admin', 'librarian', 'member'] : ['member'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'password' => [$member ? 'nullable' : 'required', 'string', 'min:10'],
        ];
    }
}
