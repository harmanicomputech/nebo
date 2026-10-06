<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserAdministration $users) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $users = User::query()
            ->with('roles')
            ->search($filters['q'] ?? null)
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->whereKey($role)))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('internal.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('internal.users.form', [
            'user' => new User,
            'roles' => $this->users->assignableRoles($request->user()),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->user(), $request->safe()->except('roles', 'password_confirmation'), $request->input('roles', []));

        return redirect()->route('app.users.edit', $user)->with('success', "{$user->name}'s account was created.");
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('view', $user);
        $user->load('roles');

        $assignable = $this->users->assignableRoles($request->user());

        return view('internal.users.form', [
            'user' => $user,
            // Show the user's current roles even if the actor could not grant them.
            'roles' => $assignable->merge($user->roles)->unique('id')->sortBy('name')->values(),
            'assignable' => $assignable->pluck('id')->all(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        // Roles are only touched when the form showed the role picker.
        $roles = $request->boolean('roles_submitted') ? $request->input('roles', []) : null;
        $this->users->update($request->user(), $user, $request->safe()->except('roles'), $roles);

        return back()->with('success', 'Changes saved.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);
        $active = $request->boolean('active');

        $this->users->setActive($request->user(), $user, $active);

        return back()->with('success', $active ? "{$user->name} can sign in again." : "{$user->name} has been deactivated and signed out.");
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);

        $this->users->resetPassword($request->user(), $user, $request->string('password'));

        return back()->with('success', "{$user->name}'s password was reset. Share it with them securely.");
    }
}
