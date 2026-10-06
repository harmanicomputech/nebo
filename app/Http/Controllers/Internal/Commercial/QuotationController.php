<?php

namespace App\Http\Controllers\Internal\Commercial;

use App\Enums\QuotationStatus;
use App\Enums\QuoteSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\QuotationRequest;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\ProductionPackage;
use App\Models\Quotation;
use App\Models\Service;
use App\Services\Commercial\QuotationService;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(private QuotationService $quotes) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quotation::class);
        $views = ['open' => 'Open', 'draft' => 'Drafts', 'sent' => 'Awaiting answer', 'won' => 'Accepted', 'closed' => 'Closed', 'all' => 'All'];
        $view = array_key_exists((string) $request->query('view'), $views) ? $request->query('view') : 'open';
        $filters = $request->only(['q', 'preparer']);

        $quotes = Quotation::query()
            ->search($filters['q'] ?? null)
            ->when($filters['preparer'] ?? null, fn ($q, $p) => $q->where('prepared_by', $p))
            ->when($view === 'open', fn ($q) => $q->whereIn('status', [QuotationStatus::Draft, QuotationStatus::Sent]))
            ->when($view === 'draft', fn ($q) => $q->where('status', QuotationStatus::Draft))
            ->when($view === 'sent', fn ($q) => $q->where('status', QuotationStatus::Sent))
            ->when($view === 'won', fn ($q) => $q->where('status', QuotationStatus::Accepted))
            ->when($view === 'closed', fn ($q) => $q->whereIn('status', [QuotationStatus::Declined, QuotationStatus::Expired, QuotationStatus::Cancelled]))
            ->with(['customer', 'preparer'])->latest('updated_at')
            ->paginate(25)->withQueryString();

        $monthStart = now(config('nebo.display_timezone'))->startOfMonth()->utc();

        return view('internal.commercial.quotations.index', [
            'quotes' => $quotes, 'view' => $view, 'views' => $views, 'filters' => $filters,
            'stats' => [
                'drafts' => Quotation::where('status', QuotationStatus::Draft)->count(),
                'awaiting' => Quotation::where('status', QuotationStatus::Sent)->count(),
                'awaitingValue' => (int) Quotation::where('status', QuotationStatus::Sent)->sum('total_kobo'),
                'wonMonth' => (int) Quotation::where('status', QuotationStatus::Accepted)->where('responded_at', '>=', $monthStart)->sum('total_kobo'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Quotation::class);
        $source = $request->query('request') ? EventRequest::with('customer')->findOrFail($request->query('request')) : null;
        $event = $request->query('event') ? Event::with('customer')->findOrFail($request->query('event')) : null;
        $customer = $source?->customer ?? $event?->customer ?? ($request->query('customer') ? Customer::find($request->query('customer')) : null);

        $quote = new Quotation([
            'customer_id' => $customer?->id,
            'event_request_id' => $source?->id ?? $event?->event_request_id,
            'event_id' => $event?->id,
            'title' => $event?->name ?? $source?->event_name ?? '',
            'valid_until' => now(config('nebo.display_timezone'))->addDays((int) Settings::get('quotations.validity_days')),
            'tax_rate_bp' => QuotationService::defaultTaxRateBp(),
            'terms' => Settings::string('quotations.terms'),
        ]);

        $lines = [];
        if ($request->query('package') && ($package = ProductionPackage::with('items')->find($request->query('package')))) {
            $lines = $package->items->map(fn ($i) => $i->only(['section', 'description', 'service_id', 'equipment_id', 'quantity', 'days', 'unit_price_kobo']))->all();
        } elseif ($source || $event) {
            // Start from the services the customer asked for.
            $services = ($event ?? $source)->services()->get();
            $lines = $services->map(fn ($s) => ['section' => 'services', 'description' => $s->name, 'service_id' => $s->id, 'quantity' => 1, 'days' => 1, 'unit_price_kobo' => 0])->all();
        }

        return view('internal.commercial.quotations.form', $this->formData($quote, $lines) + ['customer' => $customer]);
    }

    public function store(QuotationRequest $request): RedirectResponse
    {
        $customer = Customer::findOrFail($request->validated('customer_id'));
        $quote = $this->quotes->create($request->user(), $customer, $request->quoteData());

        return redirect()->route('app.quotations.show', $quote)->with('success', "Quotation {$quote->reference} saved as a draft.");
    }

    public function show(Request $request, Quotation $quotation): View
    {
        $this->authorize('view', $quotation);
        $quotation->load(['customer', 'request', 'event', 'preparer', 'sender', 'items', 'statusChanges', 'notes', 'documents']);

        return view('internal.commercial.quotations.show', [
            'quote' => $quotation,
            'packages' => $request->user()->can('update', $quotation) ? ProductionPackage::options() : [],
            'publicUrl' => route('quotations.public', $quotation->public_token),
        ]);
    }

    public function edit(Quotation $quotation): View
    {
        $this->authorize('update', $quotation);
        $quotation->load(['customer', 'items']);

        return view('internal.commercial.quotations.form', $this->formData($quotation, $quotation->items->map(fn ($i) => $i->only(['section', 'description', 'service_id', 'equipment_id', 'quantity', 'days', 'unit_price_kobo']))->all()) + ['customer' => $quotation->customer]);
    }

    public function update(QuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->quotes->update($quotation, $request->quoteData());

        return redirect()->route('app.quotations.show', $quotation)->with('success', 'Quotation saved.');
    }

    public function addPackage(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('update', $quotation);
        $data = $request->validate(['package_id' => ['required', Rule::exists('production_packages', 'id')->whereNull('deleted_at')]]);
        $package = ProductionPackage::findOrFail($data['package_id']);
        $this->quotes->addPackage($quotation, $package);

        return back()->with('success', "{$package->name} added.");
    }

    public function send(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('send', $quotation);
        $this->quotes->send($request->user(), $quotation);

        return back()->with('success', "{$quotation->reference} approved and sent. Share the customer link if email isn't set up.");
    }

    public function respond(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('respond', $quotation);
        $data = $request->validate([
            'answer' => ['required', 'in:accepted,declined'],
            'name' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->quotes->respond($request->user(), $quotation, $data['answer'] === 'accepted', $data['name'], $data['note'] ?? null);

        return back()->with('success', 'Answer recorded.');
    }

    public function revise(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('revise', $quotation);
        $this->quotes->revise($request->user(), $quotation);

        return redirect()->route('app.quotations.edit', $quotation)->with('success', "Revision {$quotation->revision} started. The previous link no longer accepts answers.");
    }

    public function cancel(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('cancel', $quotation);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $this->quotes->cancel($request->user(), $quotation, $data['note']);

        return back()->with('success', "{$quotation->reference} cancelled.");
    }

    public function duplicate(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('create', Quotation::class);
        $copy = $this->quotes->duplicate($request->user(), $quotation);

        return redirect()->route('app.quotations.edit', $copy)->with('success', "Copied to {$copy->reference}.");
    }

    public function print(Quotation $quotation): View
    {
        $this->authorize('view', $quotation);

        return view('quotations.document', ['quote' => $quotation->load(['customer', 'items']), 'internal' => true]);
    }

    public function note(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('addNote', $quotation);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $quotation->notes()->create(['body' => $data['body'], 'user_id' => $request->user()->id, 'user_name' => $request->user()->name]);

        return back()->with('success', 'Note added.');
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function formData(Quotation $quote, array $lines): array
    {
        return [
            'quote' => $quote,
            'lines' => collect($lines)->map(fn ($l) => [
                'section' => $l['section'] instanceof QuoteSection ? $l['section']->value : $l['section'],
                'description' => $l['description'], 'quantity' => $l['quantity'], 'days' => $l['days'],
                'unit_price' => Format::nairaInput($l['unit_price_kobo']), 'service_id' => $l['service_id'] ?? null, 'equipment_id' => $l['equipment_id'] ?? null,
            ])->values()->all(),
            'sections' => QuoteSection::options(),
            'catalogue' => self::catalogue(),
            'customers' => $quote->exists ? [] : Customer::query()->orderBy('company')->orderBy('name')->limit(1000)->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all(),
        ];
    }

    /**
     * Pickers for the line editor: equipment with day rates, services.
     *
     * @return array{equipment: list<array<string, mixed>>, services: list<array<string, mixed>>}
     */
    public static function catalogue(): array
    {
        return [
            'equipment' => Equipment::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'day_rate_kobo'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'rate' => Format::nairaInput($e->day_rate_kobo)])->all(),
            'services' => Service::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all(),
        ];
    }
}
