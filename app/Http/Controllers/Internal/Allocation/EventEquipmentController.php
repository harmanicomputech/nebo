<?php

namespace App\Http\Controllers\Internal\Allocation;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentRequirement;
use App\Models\Event;
use App\Services\Allocation\AllocationService;
use App\Services\Allocation\RequirementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Requirements and allocation on an event (brief §24–25). */
class EventEquipmentController extends Controller
{
    public function __construct(private RequirementService $requirements, private AllocationService $allocations) {}

    public function setRequirement(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageRequirements', $event);
        $data = $request->validate([
            'equipment_id' => ['required', 'integer', Rule::exists('equipment', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $req = $this->requirements->set($event, Equipment::findOrFail($data['equipment_id']), $data['quantity'], $data['notes'] ?? null);

        return redirect()->route('app.events.show', [$event, 'tab' => 'equipment'])->with('success', "{$req->quantity} × {$req->equipment->name} required.");
    }

    public function removeRequirement(Event $event, EquipmentRequirement $requirement): RedirectResponse
    {
        $this->authorize('manageRequirements', $event);
        $this->requirements->remove($event, $requirement);

        return back()->with('success', 'Requirement removed.');
    }

    public function allocate(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('allocate', $event);
        $data = $request->validate([
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'mode' => ['required', 'in:auto,assets,bulk'],
            'quantity' => ['nullable', 'required_if:mode,auto,bulk', 'integer', 'min:1', 'max:100000'],
            'assets' => ['nullable', 'required_if:mode,assets', 'array'],
            'assets.*' => ['integer'],
        ]);
        $equipment = Equipment::findOrFail($data['equipment_id']);
        $user = $request->user();

        $count = match ($data['mode']) {
            'auto' => $this->allocations->autoReserve($user, $event, $equipment, (int) $data['quantity'])->count(),
            'assets' => $this->allocations->reserveAssets($user, $event, $equipment, $data['assets'])->count(),
            'bulk' => $this->allocations->reserveBulk($user, $event, $equipment, (int) $data['quantity'])->quantity,
        };

        return redirect()->route('app.events.show', [$event, 'tab' => 'equipment'])->with('success', "{$count} × {$equipment->name} allocated.");
    }

    public function release(Request $request, Event $event, EquipmentAllocation $allocation): RedirectResponse
    {
        $this->authorize('allocate', $event);
        abort_unless($allocation->event_id === $event->id, 404);

        $this->allocations->release($request->user(), $allocation, $request->string('reason')->limit(300)->toString() ?: null);

        return back()->with('success', 'Released.');
    }
}
