<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Event;
use App\Models\EventRequest;
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
        if (Quotation::withTrashed()->exists()) {
            return;
        }

        $admin = User::where('email', 'admin@nebostage.test')->firstOrFail();
        auth()->login($admin);

        foreach ([1 => ['corporate', 'Lagos', 'Lagos'], 2 => ['religious', 'Abuja', 'FCT'], 3 => ['individual', 'Enugu', 'Enugu'], 4 => ['corporate', 'Lagos', 'Lagos'], 5 => ['agency', 'Port Harcourt', 'Rivers'], 6 => ['corporate', 'Lagos', 'Lagos']] as $id => [$type, $city, $state]) {
            Customer::whereKey($id)->update(['type' => $type, 'city' => $city, 'state' => $state]);
        }

        // Day rates (₦) for the demo catalogue.
        $rates = ['DEMO-ML-01' => 45000, 'DEMO-MW-02' => 35000, 'DEMO-S4-03' => 8000, 'DEMO-FS-04' => 25000, 'DEMO-GMA-05' => 150000, 'DEMO-CL-06' => 120000,
            'DEMO-HZ-07' => 15000, 'DEMO-DIS-08' => 30000, 'DEMO-DIM-09' => 40000, 'DEMO-HST-10' => 20000, 'DEMO-COM-11' => 12000,
            'DEMO-BLK-01' => 6000, 'DEMO-BLK-02' => 1500, 'DEMO-BLK-03' => 1000, 'DEMO-BLK-04' => 1000, 'DEMO-BLK-05' => 500, 'DEMO-BLK-06' => 2000];
        foreach ($rates as $sku => $naira) {
            Equipment::where('sku', $sku)->update(['day_rate_kobo' => $naira * 100]);
        }

        $eq = fn (string $sku) => Equipment::where('sku', $sku)->first();
        $svc = fn (string $name) => Service::where('name', $name)->value('id');
        $line = fn (string $section, string $description, int $qty, int $days, int $naira, array $extra = []) => ['section' => $section, 'description' => $description, 'quantity' => $qty, 'days' => $days, 'unit_price_kobo' => $naira * 100] + $extra;

        $conference = $packages->save(null, ['name' => 'Conference AV — up to 500 guests', 'event_type' => 'conference', 'is_active' => true, 'sort_order' => 10,
            'description' => 'Stage wash and key lighting, speech PA with wireless mics, FOH mixing, two 4 m screens and a technical crew of six.',
            'items' => [
                $line('services', 'Technical production management', 1, 1, 450000, ['service_id' => $svc('Full Event Production')]),
                $line('equipment', 'Robe MegaPointe moving heads', 8, 1, 45000, ['equipment_id' => $eq('DEMO-ML-01')?->id]),
                $line('equipment', 'ETC Source Four profiles (key light)', 12, 1, 8000, ['equipment_id' => $eq('DEMO-S4-03')?->id]),
                $line('equipment', 'Yamaha CL5 digital console', 1, 1, 120000, ['equipment_id' => $eq('DEMO-CL-06')?->id]),
                $line('services', 'Speech PA and 8 wireless microphones', 1, 1, 380000, ['service_id' => $svc('Sound & Audio Production')]),
                $line('services', 'LED screens 4 m × 2.25 m, pair, with switching', 2, 1, 550000, ['service_id' => $svc('LED Screens & Displays')]),
                $line('labour', 'Technical crew (lighting, sound, video)', 6, 2, 35000),
                $line('transport', 'Truck and van, Lagos', 1, 1, 250000),
            ]]);

        $packages->save(null, ['name' => 'Wedding stage & lighting', 'event_type' => 'wedding', 'is_active' => true, 'sort_order' => 20,
            'description' => 'Decorated stage, warm wash and pinspots, dance-floor effects and a crew of four.',
            'items' => [
                $line('services', 'Stage 8 m × 4 m with skirting and steps', 1, 1, 650000, ['service_id' => $svc('Stage & Staging')]),
                $line('equipment', 'Chauvet Maverick wash lights', 10, 1, 35000, ['equipment_id' => $eq('DEMO-MW-02')?->id]),
                $line('equipment', 'Hazer', 1, 1, 15000, ['equipment_id' => $eq('DEMO-HZ-07')?->id]),
                $line('labour', 'Lighting crew', 4, 1, 30000),
                $line('transport', 'Van, within Lagos', 1, 1, 120000),
            ]]);

        // The leadership conference request: quotation sent, awaiting the client.
        if ($request = EventRequest::where('event_name', '[Demo] Annual Leadership Conference')->with('customer')->first()) {
            $q = $quotes->create($admin, $request->customer, ['title' => $request->event_name, 'event_request_id' => $request->id,
                'valid_until' => now(config('nebo.display_timezone'))->addDays(10)->toDateString(), 'discount_kobo' => 250000_00,
                'intro' => 'Thank you for considering Nebo Stage. This quotation covers full technical production for your two-day conference.', 'items' => []]);
            $quotes->addPackage($q, $conference);
            $quotes->send($admin, $q->fresh());
        }

        // The Volt launch: accepted.
        if ($volt = Event::where('name', '[Demo] Product Launch: Volt Phone X')->with('customer')->first()) {
            $q = $quotes->create($admin, $volt->customer, ['title' => $volt->name, 'event_id' => $volt->id, 'event_request_id' => $volt->event_request_id,
                'valid_until' => now(config('nebo.display_timezone'))->addDays(14)->toDateString(), 'items' => [
                    $line('services', 'Full event production and show calling', 1, 1, 1200000, ['service_id' => $svc('Full Event Production')]),
                    $line('equipment', 'Robe MegaPointe moving heads', 24, 2, 45000, ['equipment_id' => $eq('DEMO-ML-01')?->id]),
                    $line('equipment', 'grandMA3 full-size console', 1, 2, 150000, ['equipment_id' => $eq('DEMO-GMA-05')?->id]),
                    $line('services', 'LED wall 12 m × 4 m', 1, 2, 1800000, ['service_id' => $svc('LED Screens & Displays')]),
                    $line('services', 'Livestream, 3 cameras', 1, 1, 950000, ['service_id' => $svc('Livestreaming')]),
                    $line('labour', 'Production crew', 14, 3, 35000),
                    $line('transport', 'Lagos → Abuja haulage, two trucks, return', 2, 1, 1100000),
                ]]);
            $quotes->send($admin, $q);
            $quotes->respond(null, $q->fresh(), true, 'Ngozi Eze', 'PO to follow from procurement.');
        }

        // A draft for the wedding.
        if ($wedding = EventRequest::where('event_name', '[Demo] Chidi & Amaka Wedding')->with('customer')->first()) {
            $quotes->create($admin, $wedding->customer, ['title' => $wedding->event_name, 'event_request_id' => $wedding->id,
                'valid_until' => now(config('nebo.display_timezone'))->addDays(14)->toDateString(), 'items' => [
                    $line('services', 'Stage 8 m × 4 m with skirting and steps', 1, 1, 650000, ['service_id' => $svc('Stage & Staging')]),
                    $line('equipment', 'Chauvet Maverick wash lights', 10, 1, 35000, ['equipment_id' => $eq('DEMO-MW-02')?->id]),
                ]]);
        }

        auth()->logout();
    }
}
