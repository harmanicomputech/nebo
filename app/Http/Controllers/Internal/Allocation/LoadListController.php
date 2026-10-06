<?php

namespace App\Http\Controllers\Internal\Allocation;

use App\Enums\LoadStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\LoadList;
use App\Models\LoadListItem;
use App\Services\Allocation\LoadListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoadListController extends Controller
{
    public function __construct(private LoadListService $lists) {}

    /** Load lists across events still to go out, or recently dispatched. */
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('allocation.view') || $request->user()->can('loadlists.manage'), 403);
        $filter = $request->query('filter') === 'dispatched' ? 'dispatched' : 'open';

        $lists = LoadList::query()
            ->whereHas('event', fn ($q) => $q->visibleTo($request->user()))
            ->with('event')->withCount(['items', 'items as checked_count' => fn ($q) => $q->where('status', LoadStatus::Checked)])
            ->when($filter === 'open', fn ($q) => $q->where('load_lists.status', '!=', LoadStatus::Dispatched))
            ->when($filter === 'dispatched', fn ($q) => $q->where('load_lists.status', LoadStatus::Dispatched))
            ->join('events', 'events.id', '=', 'load_lists.event_id')->select('load_lists.*')
            ->orderBy('events.setup_starts_at', $filter === 'open' ? 'asc' : 'desc')
            ->paginate(25)->withQueryString();

        return view('internal.allocation.load-lists', ['lists' => $lists, 'filter' => $filter]);
    }

    public function show(Event $event): View
    {
        $this->authorize('view', $event);
        abort_unless($event->loadList, 404);

        return view('internal.allocation.load-list', $this->data($event));
    }

    public function print(Event $event): View
    {
        $this->authorize('view', $event);
        abort_unless($event->loadList, 404);

        return view('internal.allocation.load-sheet', $this->data($event));
    }

    public function sync(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('workLoadList', $event);
        $list = $this->lists->sync($request->user(), $event);

        return redirect()->route('app.events.load-list', $event)->with('success', "Load list {$list->reference} is up to date.");
    }

    public function item(Request $request, Event $event, LoadListItem $item): RedirectResponse
    {
        $this->authorize('workLoadList', $event);
        abort_unless($item->loadList->event_id === $event->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, LoadStatus::itemStatuses()))],
            'case_label' => ['nullable', 'string', 'max:60'],
        ]);

        $this->lists->setItemStatus($request->user(), $item, LoadStatus::from($data['status']), $data['case_label'] ?? null);

        return back();
    }

    public function advance(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('workLoadList', $event);
        $data = $request->validate(['status' => ['required', Rule::in(['picked', 'loaded', 'checked'])]]);

        $this->lists->advanceAll($request->user(), $event->loadList, LoadStatus::from($data['status']));

        return back()->with('success', 'All items marked '.$data['status'].'.');
    }

    public function dispatch(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('workLoadList', $event);
        $this->lists->dispatch($request->user(), $event->loadList);

        return back()->with('success', 'Dispatched. Everything on the list is checked out to the event.');
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Event $event): array
    {
        $list = $event->loadList->load(['items.allocation.asset', 'items.allocation.equipment.category', 'items.allocation.location', 'items.checker', 'preparer', 'dispatcher']);

        return [
            'event' => $event->load(['team.staff', 'customer']),
            'list' => $list,
            'groups' => $list->items->groupBy(fn ($i) => $i->allocation->equipment->name)->sortKeys(),
        ];
    }
}
