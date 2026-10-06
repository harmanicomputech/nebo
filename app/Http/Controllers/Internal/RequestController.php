<?php

namespace App\Http\Controllers\Internal;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\EventRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\RequestWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequestController extends Controller
{
    public function __construct(private RequestWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EventRequest::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', 'in:open,won,closed,all'],
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'assigned' => ['nullable', 'string', 'max:20'],
            'service' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'in:newest,event_date'],
        ]);
        $view = $filters['view'] ?? 'open';
        $won = array_map(fn ($s) => $s->value, array_filter(RequestStatus::cases(), fn ($s) => $s->isWon()));

        $requests = EventRequest::query()
            ->with(['assignee', 'services'])
            ->search($filters['q'] ?? null)
            ->when($view === 'open', fn (Builder $q) => $q->open())
            ->when($view === 'won', fn (Builder $q) => $q->whereIn('status', $won))
            ->when($view === 'closed', fn (Builder $q) => $q->whereIn('status', [RequestStatus::Cancelled->value, RequestStatus::Declined->value]))
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when(($filters['assigned'] ?? null) === 'me', fn (Builder $q) => $q->where('assigned_to', $request->user()->id))
            ->when(($filters['assigned'] ?? null) === 'none', fn (Builder $q) => $q->whereNull('assigned_to'))
            ->when($filters['service'] ?? null, fn (Builder $q, $id) => $q->whereHas('services', fn ($s) => $s->whereKey($id)))
            ->when($filters['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('event_date', '>=', $d))
            ->when($filters['to'] ?? null, fn (Builder $q, $d) => $q->whereDate('event_date', '<=', $d))
            ->when(($filters['sort'] ?? 'newest') === 'event_date', fn (Builder $q) => $q->orderBy('event_date'), fn (Builder $q) => $q->latest('id'))
            ->paginate(25)->withQueryString();

        return view('internal.requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'view' => $view,
            'counts' => [
                'open' => EventRequest::query()->open()->count(),
                'new' => EventRequest::where('status', RequestStatus::New)->count(),
                'won' => EventRequest::whereIn('status', $won)->count(),
            ],
            'statuses' => collect(RequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'services' => Service::orderBy('sort_order')->pluck('name', 'id')->all(),
        ]);
    }

    public function show(EventRequest $request): View
    {
        $this->authorize('view', $request);
        $request->load(['customer', 'assignee', 'services', 'statusChanges', 'notes', 'documents']);

        return view('internal.requests.show', [
            'request' => $request,
            'transitions' => collect($request->status->transitions())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'assignees' => User::query()->active()->orderBy('name')->get()->filter(fn (User $u) => $u->can('requests.view'))->pluck('name', 'id')->all(),
            'quotes' => auth()->user()->can('quotations.view') ? $request->quotations()->get() : null,
            'otherRequests' => $request->customer ? $request->customer->requests()->whereKeyNot($request->id)->latest('id')->limit(5)->get() : collect(),
        ]);
    }

    public function status(Request $httpRequest, EventRequest $request): RedirectResponse
    {
        $this->authorize('changeStatus', $request);
        $data = $httpRequest->validate([
            'status' => ['required', Rule::enum(RequestStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $to = RequestStatus::from($data['status']);
        $this->workflow->transition($httpRequest->user(), $request, $to, $data['note'] ?? null);

        return back()->with('success', "{$request->reference} is now {$to->label()}.");
    }

    public function assign(Request $httpRequest, EventRequest $request): RedirectResponse
    {
        $this->authorize('update', $request);
        $data = $httpRequest->validate(['assigned_to' => ['nullable', 'integer', 'exists:users,id']]);

        $assignee = isset($data['assigned_to']) ? User::find($data['assigned_to']) : null;
        $this->workflow->assign($httpRequest->user(), $request, $assignee);

        return back()->with('success', $assignee ? "Assigned to {$assignee->name}." : 'Unassigned.');
    }

    public function note(Request $httpRequest, EventRequest $request): RedirectResponse
    {
        $this->authorize('update', $request);
        $data = $httpRequest->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->workflow->addNote($httpRequest->user(), $request, $data['body']);

        return back()->with('success', 'Note added.');
    }
}
