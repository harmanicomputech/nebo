<?php

namespace App\Http\Controllers\Internal\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Staff;
use App\Models\User;
use App\Support\Lookups;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request, Lookups $lookups): View
    {
        $this->authorize('viewAny', Staff::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', 'string', 'max:50'], 'status' => ['nullable', 'in:active,inactive']]);

        return view('internal.staff.index', [
            'staff' => Staff::query()->with('user')
                ->withCount(['assignments as upcoming_count' => fn ($q) => $q->whereHas('event', fn ($e) => $e->active()->where('breakdown_ends_at', '>=', now()))])
                ->search($filters['q'] ?? null)
                ->when($filters['role'] ?? null, fn ($q, $r) => $q->where('role', $r))
                ->when(($filters['status'] ?? 'active') === 'active', fn ($q) => $q->where('is_active', true), fn ($q) => $q->where('is_active', false))
                ->orderBy('name')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'roles' => $lookups->options('staff_role'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Staff::class);

        return view('internal.staff.form', $this->formData(new Staff(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Staff::class);
        $staff = Staff::create($this->validated($request));

        return redirect()->route('app.staff.show', $staff)->with('success', "{$staff->name} added.");
    }

    public function show(Staff $staff): View
    {
        $this->authorize('view', $staff);

        return view('internal.staff.show', [
            'staff' => $staff->load('user'),
            'upcoming' => Event::query()->active()->where('breakdown_ends_at', '>=', now())
                ->where(fn ($q) => $q->whereHas('team', fn ($t) => $t->where('staff_id', $staff->id))->orWhere('production_manager_id', $staff->id))
                ->with(['team' => fn ($t) => $t->where('staff_id', $staff->id)])->orderBy('setup_starts_at')->limit(20)->get(),
            'past' => Event::query()->where('breakdown_ends_at', '<', now())->whereHas('team', fn ($t) => $t->where('staff_id', $staff->id))->latest('starts_at')->limit(10)->get(),
        ]);
    }

    public function edit(Staff $staff): View
    {
        $this->authorize('update', $staff);

        return view('internal.staff.form', $this->formData($staff));
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);
        $staff->update($this->validated($request, $staff));

        return redirect()->route('app.staff.show', $staff)->with('success', 'Saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Staff $staff = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['required', Rule::in(array_merge(app(Lookups::class)->activeKeys('staff_role'), $staff ? [$staff->role] : []))],
            'phone' => ['nullable', 'string', 'max:30', fn ($a, $v, $fail) => $v && ! PhoneNumber::isValid($v) ? $fail('Enter a valid phone number.') : null],
            'email' => ['nullable', 'email', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('staff', 'user_id')->ignore($staff)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ], ['user_id.unique' => 'That user is already linked to another staff profile.']);
        $data['phone'] = PhoneNumber::normalize($data['phone'] ?? null);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Staff $staff): array
    {
        return [
            'staff' => $staff,
            'roles' => app(Lookups::class)->options('staff_role', $staff->role),
            'users' => User::query()->orderBy('name')->whereDoesntHave('staffProfile', fn ($q) => $q->when($staff->exists, fn ($w) => $w->whereKeyNot($staff->id)))->pluck('name', 'id')->all(),
        ];
    }
}
