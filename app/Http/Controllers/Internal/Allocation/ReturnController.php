<?php

namespace App\Http\Controllers\Internal\Allocation;

use App\Enums\ReturnOutcome;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Location;
use App\Services\Allocation\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns) {}

    public function show(Event $event): View
    {
        $this->authorize('view', $event);

        return view('internal.allocation.returns', [
            'event' => $event,
            'outstanding' => $this->returns->outstanding($event),
            'checks' => $event->returnChecks()->with('items.allocation.asset', 'items.allocation.equipment')->get(),
            'locations' => Location::options(),
            'defaultLocation' => Location::where('code', 'MAIN')->value('id'),
            'outcomes' => collect(ReturnOutcome::cases())->mapWithKeys(fn ($o) => [$o->value => $o->label()])->all(),
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('checkIn', $event);
        $data = $request->validate([
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'lines' => ['required', 'array'],
            'lines.*.include' => ['nullable', 'boolean'],
            'lines.*.outcome' => ['nullable', Rule::enum(ReturnOutcome::class)],
            'lines.*.returned' => ['nullable', 'integer', 'min:0'],
            'lines.*.missing' => ['nullable', 'integer', 'min:0'],
            'lines.*.damaged' => ['nullable', 'integer', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $lines = array_filter($data['lines'], fn ($l) => ! empty($l['include']));
        $check = $this->returns->process($request->user(), $event, $lines, (int) $data['location_id'], $data['notes'] ?? null);

        return redirect()->route('app.events.returns', $event)->with(
            $check->missing_count || $check->damaged_count ? 'error' : 'success',
            "Checked in: {$check->returned_count} returned, {$check->missing_count} missing, {$check->damaged_count} damaged or needing attention.",
        );
    }
}
