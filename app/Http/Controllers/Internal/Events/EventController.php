<?php

namespace App\Http\Controllers\Internal\Events;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\EventFormRequest;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Events\EventService;
use App\Services\Events\EventWorkflow;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    /** Workspace tabs (brief §23). Tabs for later modules are shown as planned. */
    public const TABS = [
        'overview' => ['Overview', null],
        'requirements' => ['Production requirements', null],
        'equipment' => ['Equipment', 5],
        'allocation' => ['Allocation', 5],
        'logistics' => ['Logistics', 7],
        'team' => ['Team', null],
        'documents' => ['Documents', null],
        'timeline' => ['Timeline', null],
        'notes' => ['Notes', null],
        'financial' => ['Financial', null],
    ];

    public function __construct(private EventService $events, private EventWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Event::class);
        $user = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'when' => ['nullable', 'in:upcoming,today,week,month,past,all'],
            'status' => ['nullable', Rule::enum(EventStatus::class)],
            'type' => ['nullable', 'string', 'max:50'],
            'manager' => ['nullable', 'integer'],
            'archived' => ['nullable', 'boolean'],
        ]);
        $when = $filters['when'] ?? 'upcoming';
        $tz = config('nebo.display_timezone');
        $now = now($tz);

        $events = Event::query()->visibleTo($user)
            ->with(['customer', 'projectManager'])
            ->withCount('team')
            ->when($filters['archived'] ?? false, fn (Builder $q) => $q->onlyTrashed())
            ->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when($filters['type'] ?? null, fn (Builder $q, $t) => $q->where('event_type', $t))
            ->when($filters['manager'] ?? null, fn (Builder $q, $m) => $q->where('project_manager_id', $m))
            ->when($when === 'upcoming', fn (Builder $q) => $q->where('breakdown_ends_at', '>=', now())->orderBy('setup_starts_at'))
            ->when($when === 'today', fn (Builder $q) => $q->overlapping($now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc())->orderBy('setup_starts_at'))
            ->when($when === 'week', fn (Builder $q) => $q->overlapping($now->copy()->startOfWeek()->utc(), $now->copy()->endOfWeek()->utc())->orderBy('setup_starts_at'))
            ->when($when === 'month', fn (Builder $q) => $q->overlapping($now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc())->orderBy('setup_starts_at'))
            ->when($when === 'past', fn (Builder $q) => $q->where('breakdown_ends_at', '<', now())->latest('starts_at'))
            ->when($when === 'all', fn (Builder $q) => $q->latest('starts_at'))
            ->paginate(25)->withQueryString();

        return view('internal.events.index', [
            'events' => $events,
            'filters' => $filters,
            'when' => $when,
            'statuses' => collect(EventStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'types' => app(Lookups::class)->options('event_type'),
            'managers' => User::query()->whereIn('id', Event::query()->whereNotNull('project_manager_id')->select('project_manager_id'))->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Event::class);

        return view('internal.events.form', $this->formData(new Event(['event_type' => 'corporate'])) + [
            'fromRequest' => null,
            'customers' => Customer::query()->orderBy('company')->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all(),
        ]);
    }

    public function store(EventFormRequest $request): RedirectResponse
    {
        $event = $this->events->create($request->user(), $request->eventData(), $request->input('services', []));

        return redirect()->route('app.events.show', $event)->with('success', "Event {$event->reference} created.");
    }

    public function show(Request $request, Event $event): View
    {
        $this->authorize('view', $event);
        $tab = array_key_exists($request->query('tab'), self::TABS) && self::TABS[$request->query('tab')][1] === null ? $request->query('tab') : 'overview';

        $event->load(['customer', 'request', 'projectManager', 'productionManager', 'services', 'team.staff', 'statusChanges', 'notes', 'documents']);

        return view('internal.events.show', [
            'event' => $event,
            'tab' => $tab,
            'tabs' => self::TABS,
            'transitions' => collect($event->status->transitions())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'staffOptions' => $tab === 'team' ? Staff::options() : [],
            'roles' => app(Lookups::class)->options('staff_role'),
        ]);
    }

    public function edit(Event $event): View
    {
        $this->authorize('update', $event);

        return view('internal.events.form', $this->formData($event) + ['fromRequest' => null, 'customers' => []]);
    }

    public function update(EventFormRequest $request, Event $event): RedirectResponse
    {
        $this->events->update($event, $request->eventData(), $request->input('services', []));

        return redirect()->route('app.events.show', $event)->with('success', 'Event saved.');
    }

    public function status(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('changeStatus', $event);
        $data = $request->validate(['status' => ['required', Rule::enum(EventStatus::class)], 'note' => ['nullable', 'string', 'max:1000']]);

        $to = EventStatus::from($data['status']);
        $this->workflow->transition($request->user(), $event, $to, $data['note'] ?? null);

        return back()->with('success', "{$event->reference} is now {$to->label()}.");
    }

    public function note(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('addNote', $event);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $event->notes()->create(['body' => $data['body'], 'user_id' => $request->user()->id, 'user_name' => $request->user()->name]);

        return back()->with('success', 'Note added.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        $event->delete();

        return redirect()->route('app.events.index')->with('success', "{$event->reference} archived.");
    }

    public function restore(int $id): RedirectResponse
    {
        $event = Event::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $event);
        $event->restore();

        return redirect()->route('app.events.show', $event)->with('success', "{$event->reference} restored.");
    }

    /** Pre-filled event form for a won request. */
    public function fromRequest(EventRequest $request): View
    {
        $this->authorize('create', Event::class);
        $tz = config('nebo.display_timezone');
        $day = $request->starts_at ?? $request->event_date->copy()->setTimezone($tz)->setTime(9, 0);
        $end = $request->ends_at ?? $request->event_date->copy()->setTimezone($tz)->addDays(max(0, $request->duration_days - 1))->setTime(22, 0);

        $draft = new Event([
            'name' => $request->event_name,
            'event_type' => $request->event_type,
            'venue' => $request->venue,
            'setup_starts_at' => $request->setup_at ?? $day->copy()->subDay(),
            'starts_at' => $day,
            'ends_at' => $end,
            'breakdown_ends_at' => $end->copy()->addHours(6),
            'production_requirements' => $request->requirements,
        ]);
        $draft->setRelation('services', $request->services);

        return view('internal.events.form', $this->formData($draft) + ['fromRequest' => $request, 'customers' => []]);
    }

    public function convert(EventFormRequest $httpRequest, EventRequest $request): RedirectResponse
    {
        $event = $this->events->convert($httpRequest->user(), $request, array_merge($httpRequest->eventData(), ['services' => $httpRequest->input('services', [])]));

        return redirect()->route('app.events.show', $event)->with('success', "Event {$event->reference} created from {$request->reference}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Event $event): array
    {
        return [
            'event' => $event,
            'types' => app(Lookups::class)->options('event_type', $event->event_type),
            'services' => Service::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'selectedServices' => $event->relationLoaded('services') || $event->exists ? $event->services->pluck('id')->all() : [],
            'managers' => User::query()->active()->orderBy('name')->get()->filter(fn ($u) => $u->can('events.view'))->pluck('name', 'id')->all(),
            'productionManagers' => Staff::options($event->production_manager_id),
        ];
    }
}
