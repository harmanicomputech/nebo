<?php

namespace Database\Seeders;

use App\Enums\TripDirection;
use App\Enums\TripStatus;
use App\Models\Event;
use App\Models\Location;
use App\Models\LogisticsTrip;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Logistics\TripService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Demo fleet and trips (never production). */
class LogisticsDemoSeeder extends Seeder
{
    /**
     * The demo fleet, created once (the history seeder uses it first).
     *
     * @return array<string, Vehicle> truck, van
     */
    public static function fleet(?Staff $driver, ?Location $main): array
    {
        $today = CarbonImmutable::now(config('nebo.display_timezone'));
        $truck = Vehicle::withTrashed()->firstOrCreate(['registration' => 'LSD 482 XA'], ['name' => 'Truck 1 (10 t)', 'type' => 'truck', 'capacity' => '10 t · 40 m³', 'payload_kg' => 10000,
            'status' => 'active', 'default_driver_id' => $driver?->id, 'base_location_id' => $main?->id, 'insurance_expires_on' => $today->addMonths(8)->toDateString(), 'roadworthiness_expires_on' => $today->addDays(18)->toDateString()]);
        $van = Vehicle::withTrashed()->firstOrCreate(['registration' => 'KJA 115 GH'], ['name' => 'Box Van 2', 'type' => 'box_van', 'capacity' => '3.5 t · 18 m³', 'payload_kg' => 3500,
            'status' => 'active', 'base_location_id' => $main?->id, 'insurance_expires_on' => $today->addMonths(3)->toDateString(), 'roadworthiness_expires_on' => $today->addMonths(5)->toDateString()]);
        Vehicle::withTrashed()->firstOrCreate(['registration' => 'AAA 903 KL'], ['name' => 'Crew Bus', 'type' => 'bus', 'capacity' => '18 seats', 'status' => 'active', 'base_location_id' => $main?->id]);
        Vehicle::withTrashed()->firstOrCreate(['registration' => 'EKY 220 BD'], ['name' => 'Truck 2 (5 t)', 'type' => 'truck', 'capacity' => '5 t', 'status' => 'out_of_service', 'remarks' => 'Gearbox rebuild at the dealer.']);

        return ['truck' => $truck, 'van' => $van];
    }

    public function run(TripService $trips): void
    {
        if (LogisticsTrip::whereHas('event', fn ($q) => $q->where('name', 'Marina Trust Town Hall'))->exists()) {
            return;
        }

        $admin = User::where('email', 'ada.okafor@nebostage.com')->firstOrFail();
        auth()->setUser($admin);
        $main = Location::where('code', 'MAIN')->first();
        $driver = Staff::where('role', 'driver')->first();
        $crewMember = Staff::where('user_id', User::where('email', 'bayo.ogun@nebostage.com')->value('id'))->first();

        [$truck, $van] = array_values(self::fleet($driver, $main));

        $base = trim(($main?->name ?? 'Main Warehouse').($main?->address ? ', '.$main->address : ''));

        // Today's town hall: delivered this morning, return booked for breakdown.
        if ($hall = Event::where('name', 'Marina Trust Town Hall')->first()) {
            $out = $trips->plan($admin, $hall, ['direction' => 'outbound', 'vehicle_id' => $truck->id, 'driver_id' => $driver?->id, 'origin' => $base, 'destination' => $hall->venue,
                'departs_at' => $hall->setup_starts_at->copy()->subHours(2), 'arrives_at' => $hall->setup_starts_at, 'crew' => array_filter([$crewMember?->id]),
                'items' => $trips->manifestCandidates($hall, TripDirection::Outbound)->modelKeys(), 'instructions' => 'Use the loading bay on the east side. Ask for Mr. Adebayo at security.']);
            $trips->transition($admin, $out, TripStatus::InTransit);
            $trips->transition($admin, $out->fresh(), TripStatus::Arrived, null, 'Tunde Bakare (venue manager)');

            $trips->plan($admin, $hall, ['direction' => 'return', 'vehicle_id' => $truck->id, 'driver_id' => $driver?->id, 'origin' => $hall->venue, 'destination' => $base,
                'departs_at' => $hall->breakdown_ends_at, 'arrives_at' => $hall->breakdown_ends_at->copy()->addHours(2), 'crew' => array_filter([$crewMember?->id]),
                'items' => $trips->manifestCandidates($hall, TripDirection::Return)->modelKeys()]);
        }

        // Jazz weekend: planned, no driver yet.
        if ($jazz = Event::where('name', 'Lagos Jazz Weekend')->first()) {
            $trips->plan($admin, $jazz, ['direction' => 'outbound', 'vehicle_id' => $van->id, 'origin' => $base, 'destination' => $jazz->venue,
                'departs_at' => $jazz->setup_starts_at->copy()->subHours(3), 'arrives_at' => $jazz->setup_starts_at,
                'items' => $trips->manifestCandidates($jazz, TripDirection::Outbound)->modelKeys()]);
        }

        auth()->forgetUser();
    }
}
