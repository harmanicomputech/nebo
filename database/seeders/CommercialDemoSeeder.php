<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\ProductionPackage;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\User;
use App\Services\Commercial\PackageService;
use App\Services\Commercial\QuotationService;
use Illuminate\Database\Seeder;

/** Demo customer details, day rates, packages and quotations (never production). */
class CommercialDemoSeeder extends Seeder
{
    public function run(PackageService $packages, QuotationService $quotes): void
    {
        if (ProductionPackage::withTrashed()->where('name', 'LED Screen 12 m² with Processing')->exists()) {
            return;
        }

        $admin = User::where('email', 'ada.okafor@nebostage.com')->firstOrFail();
        auth()->setUser($admin);

        // Sample day rates on the real equipment; the clear restores the equipment as it was (D73).
        foreach (HistoryDemoSeeder::RATES as $sku => $naira) {
            Equipment::where('sku', $sku)->update(['day_rate_kobo' => $naira * 100]);
        }

        $eq = fn (string $sku) => Equipment::where('sku', $sku)->first();
        $svc = fn (string $name) => Service::where('name', $name)->value('id');
        $line = fn (string $section, string $description, int $qty, int $days, int $naira, array $extra = []) => ['section' => $section, 'description' => $description, 'quantity' => $qty, 'days' => $days, 'unit_price_kobo' => $naira * 100] + $extra;

        $screen = $packages->save(null, ['name' => 'LED Screen 12 m² with Processing', 'event_type' => 'conference', 'is_active' => true, 'sort_order' => 10,
            'description' => 'A 4 m × 3 m P3.91 outdoor-grade screen, flown or ground-stacked, with a VX600 Pro processor, rigging and a crew of four.',
            'items' => [
                $line('equipment', 'P3.91 LED panels 0.5 m × 1 m (12 m²)', 24, 1, 20000, ['equipment_id' => $eq('LED-P391-L')?->id]),
                $line('equipment', 'Video processor VX600 Pro', 1, 1, 60000, ['equipment_id' => $eq('VID-VX600')?->id]),
                $line('equipment', 'Aluminium spigot truss 400 × 400, 3 m', 6, 1, 15000, ['equipment_id' => $eq('TRS-44-3M')?->id]),
                $line('equipment', 'Manual chain hoists', 2, 1, 20000, ['equipment_id' => $eq('RIG-HOIST')?->id]),
                $line('services', 'Screen operation and content playback', 1, 1, 150000, ['service_id' => $svc('LED Screens & Displays')]),
                $line('labour', 'Screen technicians', 4, 2, 35000),
                $line('transport', 'Van, within Lagos', 1, 1, 120000),
            ]]);

        $packages->save(null, ['name' => 'Outdoor Stage with Roof 10 m × 8 m', 'event_type' => 'festival', 'is_active' => true, 'sort_order' => 20,
            'description' => '50 stage decks under a 400 × 600 truss roof on six towers, with stairs, and a rigging crew of eight.',
            'items' => [
                $line('equipment', 'Stage panels', 50, 1, 12000, ['equipment_id' => $eq('STG-PANEL')?->id]),
                $line('equipment', 'Aluminium spigot truss 400 × 600, 3 m', 16, 1, 20000, ['equipment_id' => $eq('TRS-46-3M')?->id]),
                $line('equipment', 'Roof towers: top sections, sleeve blocks and bases', 6, 1, 33000),
                $line('equipment', 'Manual chain hoists', 6, 1, 20000, ['equipment_id' => $eq('RIG-HOIST')?->id]),
                $line('equipment', 'Staircases', 2, 1, 15000, ['equipment_id' => $eq('STG-STAIR')?->id]),
                $line('labour', 'Riggers and stage crew', 8, 2, 35000),
                $line('transport', 'Truck, within Lagos', 1, 1, 250000),
            ]]);

        // The leadership conference request: quotation sent, awaiting the client.
        if ($request = EventRequest::where('event_name', 'Annual Leadership Conference')->with('customer')->first()) {
            $q = $quotes->create($admin, $request->customer, ['title' => $request->event_name, 'event_request_id' => $request->id,
                'valid_until' => now(config('nebo.display_timezone'))->addDays(10)->toDateString(), 'discount_kobo' => 250000_00,
                'intro' => 'Thank you for considering Nebo Stage. This quotation covers the stage screen and its crew for your two-day conference.', 'items' => []]);
            $quotes->addPackage($q, $screen);
            $quotes->send($admin, $q->fresh());
        }

        // The Volt launch: accepted.
        if ($volt = Event::where('name', 'Product Launch: Volt Phone X')->with('customer')->first()) {
            $q = $quotes->create($admin, $volt->customer, ['title' => $volt->name, 'event_id' => $volt->id, 'event_request_id' => $volt->event_request_id,
                'valid_until' => now(config('nebo.display_timezone'))->addDays(14)->toDateString(), 'items' => [
                    $line('services', 'Full event production and show calling', 1, 1, 1200000, ['service_id' => $svc('Full Event Production')]),
                    $line('equipment', 'P3.91 LED panels 0.5 m × 0.5 m (16 m² centre screen)', 64, 2, 10000, ['equipment_id' => $eq('LED-P391-S')?->id]),
                    $line('equipment', 'Video processors VX600 Pro', 2, 2, 60000, ['equipment_id' => $eq('VID-VX600')?->id]),
                    $line('equipment', 'Stage panels', 30, 2, 12000, ['equipment_id' => $eq('STG-PANEL')?->id]),
                    $line('services', 'Livestream, 3 cameras', 1, 1, 950000, ['service_id' => $svc('Livestreaming')]),
                    $line('labour', 'Production crew', 14, 3, 35000),
                    $line('transport', 'Lagos → Abuja haulage, two trucks, return', 2, 1, 1100000),
                ]]);
            $quotes->send($admin, $q);
            $quotes->respond(null, $q->fresh(), true, 'Ngozi Eze', 'PO to follow from procurement.');
        }

        // A draft for the wedding.
        if ($wedding = EventRequest::where('event_name', 'Chidi & Amaka Wedding')->with('customer')->first()) {
            $quotes->create($admin, $wedding->customer, ['title' => $wedding->event_name, 'event_request_id' => $wedding->id,
                'valid_until' => now(config('nebo.display_timezone'))->addDays(14)->toDateString(), 'items' => [
                    $line('equipment', 'Stage panels (8 m × 4 m stage)', 32, 1, 12000, ['equipment_id' => $eq('STG-PANEL')?->id]),
                    $line('equipment', 'Staircase', 1, 1, 15000, ['equipment_id' => $eq('STG-STAIR')?->id]),
                    $line('equipment', 'P3.91 LED panels 0.5 m × 1 m (backdrop screen)', 12, 1, 20000, ['equipment_id' => $eq('LED-P391-L')?->id]),
                ]]);
        }

        auth()->forgetUser();
    }
}
