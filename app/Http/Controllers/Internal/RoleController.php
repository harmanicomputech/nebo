<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\RoleRequest;
use App\Models\Role;
use App\Services\RoleAdministration;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private RoleAdministration $roles) {}

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('internal.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('name')->get(),
            'totalPermissions' => count(PermissionCatalog::all()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('internal.roles.form', ['role' => new Role, 'modules' => PermissionCatalog::modules(), 'granted' => []]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = $this->roles->create($request->user(), $request->safe()->only('name', 'description'), $request->input('permissions', []));

        return redirect()->route('app.roles.edit', $role)->with('success', "Role {$role->name} created.");
    }

    public function edit(Role $role): View
    {
        $this->authorize('view', $role);

        return view('internal.roles.form', [
            'role' => $role->loadCount('users'),
            'modules' => PermissionCatalog::modules(),
            'granted' => $role->name === PermissionCatalog::SUPER_ADMIN ? PermissionCatalog::all() : $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($request->user(), $role, $request->safe()->only('name', 'description'), $request->input('permissions', []));

        return back()->with('success', 'Role saved.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);
        $this->roles->delete($role);

        return redirect()->route('app.roles.index')->with('success', "Role {$role->name} deleted.");
    }
}
