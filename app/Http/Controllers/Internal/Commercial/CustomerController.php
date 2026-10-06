<?php

namespace App\Http\Controllers\Internal\Commercial;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\CustomerRequest;
use App\Models\Customer;
use App\Services\Crm\CustomerService;
use App\Support\Lookups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $customers, private Lookups $lookups) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);
        $filters = $request->only(['q', 'type', 'review', 'sort']);
        $sort = in_array($filters['sort'] ?? null, ['name', 'recent', 'value'], true) ? $filters['sort'] : 'recent';
        $seesMoney = $request->user()->can('financial.view');

        $customers = Customer::query()
            ->search($filters['q'] ?? null)
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['review'] ?? false, fn ($q) => $q->where('needs_review', true))
            ->withCount(['requests', 'events', 'quotations'])
            ->withSum(['quotations as won_kobo' => fn ($q) => $q->where('status', QuotationStatus::Accepted)], 'total_kobo')
            ->when($sort === 'name', fn ($q) => $q->orderBy('company')->orderBy('name'))
            ->when($sort === 'recent', fn ($q) => $q->latest('updated_at'))
            ->when($sort === 'value' && $seesMoney, fn ($q) => $q->orderByDesc('won_kobo'))
            ->paginate(25)->withQueryString();

        return view('internal.commercial.customers.index', [
            'customers' => $customers, 'filters' => $filters, 'sort' => $sort, 'seesMoney' => $seesMoney,
            'types' => $this->lookups->options('customer_type'),
            'reviewCount' => Customer::where('needs_review', true)->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('internal.commercial.customers.form', ['customer' => new Customer, 'types' => $this->lookups->options('customer_type')]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = $this->customers->save(null, $request->validated());

        return redirect()->route('app.customers.show', $customer)->with('success', "{$customer->displayName()} added.");
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorize('view', $customer);
        $customer->load(['notes', 'documents']);
        $user = $request->user();

        return view('internal.commercial.customers.show', [
            'customer' => $customer,
            'requests' => $user->can('requests.view') ? $customer->requests()->latest('id')->limit(20)->get() : collect(),
            'events' => $user->can('events.view') ? $customer->events()->latest('starts_at')->limit(20)->get() : collect(),
            'quotations' => $user->can('quotations.view') ? $customer->quotations()->latest('id')->limit(20)->get() : collect(),
            'stats' => [
                'won' => (int) $customer->quotations()->where('status', QuotationStatus::Accepted)->sum('total_kobo'),
                'open' => (int) $customer->quotations()->where('status', QuotationStatus::Sent)->sum('total_kobo'),
                'events' => $customer->events()->count(),
                'requests' => $customer->requests()->count(),
            ],
            'mergeOptions' => $user->can('merge', $customer)
                ? Customer::query()->whereKeyNot($customer->id)->orderBy('company')->orderBy('name')->limit(500)->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName().($c->email ? " · {$c->email}" : '')])->all()
                : [],
            'similar' => $customer->needs_review ? $this->similar($customer) : collect(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('internal.commercial.customers.form', ['customer' => $customer, 'types' => $this->lookups->options('customer_type', $customer->type)]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customers->save($customer, $request->validated());

        return redirect()->route('app.customers.show', $customer)->with('success', 'Customer saved.');
    }

    public function reviewed(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->customers->markReviewed($customer);

        return back()->with('success', 'Marked as checked.');
    }

    /** Merge another profile (the duplicate) into this one. */
    public function merge(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('merge', $customer);
        $data = $request->validate(['duplicate_id' => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')]]);
        $duplicate = Customer::findOrFail($data['duplicate_id']);
        $this->customers->merge($request->user(), $customer, $duplicate);

        return redirect()->route('app.customers.show', $customer)->with('success', "{$duplicate->displayName()} merged into this profile.");
    }

    public function note(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $customer->notes()->create(['body' => $data['body'], 'user_id' => $request->user()->id, 'user_name' => $request->user()->name]);

        return back()->with('success', 'Note added.');
    }

    /** Profiles sharing the email, phone or company: likely duplicates. */
    private function similar(Customer $customer)
    {
        return Customer::query()->whereKeyNot($customer->id)
            ->where(fn ($q) => $q->when($customer->email, fn ($w) => $w->orWhere('email', $customer->email))
                ->when($customer->phone, fn ($w) => $w->orWhere('phone', $customer->phone))
                ->when($customer->company, fn ($w) => $w->orWhere('company', $customer->company)))
            ->limit(5)->get();
    }
}
