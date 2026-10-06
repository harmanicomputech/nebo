<?php

namespace App\Http\Requests\Internal;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create and edit user accounts. Whether the actor may assign the chosen
 * roles is decided by UserAdministration, not here.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? $this->user()->can('update', $user)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'password' => $user ? ['prohibited'] : ['required', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter a valid phone number.'];
    }
}
