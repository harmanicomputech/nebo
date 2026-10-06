<?php

namespace App\Http\Controllers\Internal\Maintenance;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\CompleteJobRequest;
use App\Http\Requests\Maintenance\MaintenanceJobRequest;
use App\Models\EquipmentAsset;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\Staff;
use App\Services\Maintenance\MaintenanceService;
use App\Support\Lookups;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $maintenance, private Lookups $lookups) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MaintenanceRecord::class);
        $user = $request->user();
        $seesAll = $user->can('maintenance.view');
        $views = $seesAll ? ['open' => 'Open jobs', 'due' => 'Due', 'schedules' => 'Schedules', 'closed' => 'Closed'] : ['open' => 'Open jobs', 'closed' => 'Closed'];
        $view = array_key_exists((string) $request->query('view'), $views) ? $request->query('view') : 'open';
        $today = CarbonImmutable::now(config('nebo.display_timezone'))->startOfDay();
        $filters = $request->only(['q', 'type', 'priority', 'technician']);

        $data = ['view' => $view, 'views' => $views, 'filters' => $filters, 'today' => $today,
            'types' => $this->lookups->options('maintenance_type'), 'priorities' => MaintenancePriority::options(), 'technicians' => Staff::options()];

        if (in_array($view, ['open', 'closed'], true)) {
            $data['records'] = MaintenanceRecord::query()->visibleTo($user)
                ->when($view === 'open', fn ($q) => $q->open(), fn ($q) => $q->whereNotIn('status', MaintenanceStatus::openValues()))
                ->search($filters['q'] ?? null)
                ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
                ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
                ->when($filters['technician'] ?? null, fn ($q, $t) => $q->where('technician_id', $t))
                ->with(['asset', 'equipment', 'technician'])
                ->when($view === 'open',
                    fn ($q) => $q->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->orderByRaw('coalesce(scheduled_starts_at, created_at)'),
                    fn ($q) => $q->latest('updated_at'))
                ->paginate(25)->withQueryString();
        } else {
            $horizon = $view === 'due' ? $today->addDays(max(30, (int) Settings::get('maintenance.reminder_days'))) : null;
            $data['schedules'] = MaintenanceSchedule::query()
                ->when($horizon, fn ($q) => $q->dueBy($horizon->toDateString()), fn ($q) => $q->whereHas('asset'))
                ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('asset', fn ($a) => $a->where('asset_tag', 'like', '%'.$term.'%')->orWhereHas('equipment', fn ($e) => $e->where('name', 'like', '%'.$term.'%'))))
                ->with(['asset.equipment', 'records' => fn ($q) => $q->open()])
                ->orderBy('next_due_on')->orderBy('asset_id')->paginate(25)->withQueryString();
        }

        $data['stats'] = [
            'open' => MaintenanceRecord::query()->visibleTo($user)->open()->count(),
            'in_progress' => MaintenanceRecord::query()->visibleTo($user)->where('status', MaintenanceStatus::InProgress)->count(),
            'urgent' => MaintenanceRecord::query()->visibleTo($user)->open()->whereIn('priority', ['urgent', 'high'])->count(),
            'overdue' => $seesAll ? MaintenanceSchedule::query()->dueBy($today->subDay()->toDateString())->count() : null,
        ];

        return view('internal.maintenance.index', $data);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', MaintenanceRecord::class);
        $asset = $request->query('asset') ? EquipmentAsset::with('equipment')->where('asset_tag', $request->query('asset'))->first() : null;

        return view('internal.maintenance.create', [
            'asset' => $asset,
            'types' => $this->lookups->options('maintenance_type'),
            'priorities' => MaintenancePriority::options(),
            'technicians' => Staff::options(),
        ]);
    }

    public function store(MaintenanceJobRequest $request): RedirectResponse
    {
        $asset = EquipmentAsset::with(['status', 'equipment'])->where('asset_tag', $request->validated('asset_tag'))->firstOrFail();
        $record = $this->maintenance->report($request->user(), $asset, $request->jobData());

        return redirect()->route('app.maintenance.show', $record)->with('success', "{$record->reference} logged for {$asset->asset_tag}.");
    }

    public function show(MaintenanceRecord $record): View
    {
        $this->authorize('view', $record);
        $record->load(['asset.status', 'asset.location', 'equipment', 'technician', 'reporter', 'event', 'schedule', 'notes', 'documents', 'statusChanges']);

        return view('internal.maintenance.show', [
            'record' => $record,
            'bookings' => $record->asset->allocations()->active()->with('event')->orderBy('hold_starts_at')->get(),
            'conditions' => $this->lookups->options('condition', $record->outcome_condition),
            'technicians' => Staff::options($record->technician_id),
        ]);
    }

    public function schedule(Request $request, MaintenanceRecord $record): RedirectResponse
    {
        $this->authorize('update', $record);
        $request->merge(collect(['scheduled_starts_at', 'scheduled_ends_at'])->mapWithKeys(fn ($f) => [$f => $this->toUtc($request->input($f))])->all());
        $data = $request->validate([
            'scheduled_starts_at' => ['required', 'date'],
            'scheduled_ends_at' => ['required', 'date', 'after:scheduled_starts_at'],
            'technician_id' => ['nullable', Rule::exists(Staff::class, 'id')->whereNull('deleted_at')],
        ]);

        $this->maintenance->schedule($request->user(), $record, CarbonImmutable::parse($data['scheduled_starts_at'], 'UTC'), CarbonImmutable::parse($data['scheduled_ends_at'], 'UTC'), $data['technician_id'] ?? null);

        return back()->with('success', "{$record->reference} scheduled.");
    }

    public function start(Request $request, MaintenanceRecord $record): RedirectResponse
    {
        $this->authorize('update', $record);
        $this->maintenance->start($request->user(), $record, $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null);

        return back()->with('success', "Work started. {$record->asset->asset_tag} is now Under Maintenance.");
    }

    public function complete(CompleteJobRequest $request, MaintenanceRecord $record): RedirectResponse
    {
        $this->maintenance->complete($request->user(), $record, $request->completion());

        return back()->with('success', "{$record->reference} completed.");
    }

    public function cancel(Request $request, MaintenanceRecord $record): RedirectResponse
    {
        $this->authorize('update', $record);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $this->maintenance->cancel($request->user(), $record, $data['note']);

        return back()->with('success', "{$record->reference} cancelled.");
    }

    public function note(Request $request, MaintenanceRecord $record): RedirectResponse
    {
        $this->authorize('addNote', $record);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $record->notes()->create(['body' => $data['body'], 'user_id' => $request->user()->id, 'user_name' => $request->user()->name]);

        return back()->with('success', 'Note added.');
    }

    private function toUtc(mixed $value): mixed
    {
        try {
            return filled($value) ? CarbonImmutable::parse((string) $value, config('nebo.display_timezone'))->utc()->toDateTimeString() : null;
        } catch (\Throwable) {
            return $value;
        }
    }
}
