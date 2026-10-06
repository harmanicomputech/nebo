<?php

namespace App\Services\Allocation;

use App\Models\Equipment;
use App\Models\EquipmentRequirement;
use App\Models\Event;
use App\Support\Audit\Audit;
use Illuminate\Validation\ValidationException;

class RequirementService
{
    public function set(Event $event, Equipment $equipment, int $quantity, ?string $notes = null): EquipmentRequirement
    {
        if ($event->status->isClosed() || $event->trashed()) {
            throw ValidationException::withMessages(['equipment_id' => 'This event is closed.']);
        }

        if ($quantity < 1 || $quantity > 100000) {
            throw ValidationException::withMessages(['quantity' => 'Enter a quantity of at least 1.']);
        }

        $requirement = $event->requirements()->updateOrCreate(['equipment_id' => $equipment->id], ['quantity' => $quantity, 'notes' => $notes]);
        Audit::record('requirement_set', "{$event->reference} needs {$quantity} × {$equipment->name}", $event);

        return $requirement;
    }

    public function remove(Event $event, EquipmentRequirement $requirement): void
    {
        abort_unless($requirement->event_id === $event->id, 404);

        if ($event->allocations()->active()->where('equipment_id', $requirement->equipment_id)->exists()) {
            throw ValidationException::withMessages(['requirement' => "Release the allocated {$requirement->equipment->name} first."]);
        }

        $requirement->delete();
        Audit::record('requirement_removed', "{$requirement->equipment->name} removed from {$event->reference} requirements", $event);
    }
}
