<?php

namespace App\Http\Controllers\Internal\Commercial;

use App\Enums\QuoteSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\PackageRequest;
use App\Models\ProductionPackage;
use App\Services\Commercial\PackageService;
use App\Support\Format;
use App\Support\Lookups;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function __construct(private PackageService $packages) {}

    public function index(): View
    {
        $this->authorize('viewAny', ProductionPackage::class);

        return view('internal.commercial.packages.index', [
            'packages' => ProductionPackage::query()->with('items')->orderByDesc('is_active')->orderBy('sort_order')->orderBy('name')->get(),
            'eventTypes' => app(Lookups::class)->options('event_type'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ProductionPackage::class);

        return view('internal.commercial.packages.form', $this->formData(new ProductionPackage(['is_active' => true])));
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        $package = $this->packages->save(null, $request->packageData());

        return redirect()->route('app.packages.index')->with('success', "{$package->name} saved.");
    }

    public function edit(ProductionPackage $package): View
    {
        $this->authorize('update', $package);

        return view('internal.commercial.packages.form', $this->formData($package->load('items')));
    }

    public function update(PackageRequest $request, ProductionPackage $package): RedirectResponse
    {
        $this->packages->save($package, $request->packageData());

        return redirect()->route('app.packages.index')->with('success', "{$package->name} saved.");
    }

    public function destroy(ProductionPackage $package): RedirectResponse
    {
        $this->authorize('delete', $package);
        $package->delete();

        return redirect()->route('app.packages.index')->with('success', "{$package->name} archived. Quotations made from it keep their lines.");
    }

    /** @return array<string, mixed> */
    private function formData(ProductionPackage $package): array
    {
        return [
            'package' => $package,
            'lines' => $package->exists ? $package->items->map(fn ($i) => [
                'section' => $i->section->value, 'description' => $i->description, 'quantity' => $i->quantity, 'days' => $i->days,
                'unit_price' => Format::nairaInput($i->unit_price_kobo), 'service_id' => $i->service_id, 'equipment_id' => $i->equipment_id,
            ])->all() : [],
            'sections' => QuoteSection::options(),
            'catalogue' => QuotationController::catalogue(),
            'eventTypes' => app(Lookups::class)->options('event_type', $package->event_type),
        ];
    }
}
