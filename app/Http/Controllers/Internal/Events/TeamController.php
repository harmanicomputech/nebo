<?php

namespace App\Http\Controllers\Internal\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventStaff;
use App\Models\Staff;
use App\Services\Events\TeamService;
use App\Support\Lookups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function __construct(private TeamService $team) {}

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageTeam', $event);
        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'role' => ['required', Rule::in(app(Lookups::class)->activeKeys('staff_role'))],
            'notes' => ['nullable', 'string', 'max:500'],
            'override_reason' => ['nullable', 'string', 'max:300'],
        ]);

        $member = $this->team->assign($event, Staff::findOrFail($data['staff_id']), $data['role'], $data['notes'] ?? null, $data['override_reason'] ?? null);

        return back()->with('success', "{$member->staff->name} added to the team.");
    }

    public function destroy(Event $event, EventStaff $member): RedirectResponse
    {
        $this->authorize('manageTeam', $event);
        $this->team->remove($event, $member);

        return back()->with('success', 'Removed from the team.');
    }
}
