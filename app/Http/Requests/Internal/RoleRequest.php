<?php

namespace App\Http\Requests\Internal;

use App\Models\Role;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? $this->user()->can('update', $role)
            : $this->user()->can('create', Role::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::all())],
        ];
    }
}
