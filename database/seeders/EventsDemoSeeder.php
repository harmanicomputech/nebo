<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\RequestStatus;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Events\EventService;
use App\Services\Events\EventWorkflow;
use App\Services\Events\TeamService;
use Illuminate\Database\Seeder;

/** Demo crew and events through the real services (never production). */
class EventsDemoSeeder extends Seeder
{
    public function run(EventService $events, EventWorkflow $workflow, TeamService $team): void
    {
        if (Event::exists()) {
            return;
        }

        $admin = User::where('email', 'ada.okafor@nebostage.com')->firstOrFail();
        auth()->setUser($admin);
        $link = fn (string $email) => User::where('email', $email)->value('id');

        $crew = collect([
            ['Ibrahim Musa', 'production_manager', $link('ibrahim.musa@nebostage.com')],
            ['Emeka Nwosu', 'lighting_technician', $link('emeka.nwosu@nebostage.com')],
            ['Bayo Ogun', 'general_crew', $link('bayo.ogun@nebostage.com')],
            ['Kelechi Obi', 'sound_engineer', null],
            ['Segun Alade', 'rigger', null],
            ['Musa Bello', 'driver', null],
            ['Ifeoma Nnaji', 'camera_operator', null],
            ['Tobi Adeyemi', 'livestream_operator', null],
            ['Halima Yusuf', 'stage_manager', null],
        ])->map(fn ($c) => Staff::create(['name' => $c[0], 'role' => $c[1], 'user_id' => $c[2], 'phone' => '+234'.(8030000000 + crc32($c[0]) % 69999999), 'is_active' => true]));

        $tz = config('nebo.display_timezone');
        $window = fn (int $days, int $length = 1) => [
            'setup_starts_at' => now($tz)->addDays($days - 1)->setTime(8, 0)->utc(),
            'starts_at' => now($tz)->addDays($days)->setTime(10, 0)->utc(),
            'ends_at' => now($tz)->addDays($days + $length - 1)->setTime(22, 0)->utc(),
            'breakdown_ends_at' => now($tz)->addDays($days + $length)->setTime(4, 0)->utc(),
        ];

        // From the approved demo request.
        $approved = EventRequest::where('status', RequestStatus::Approved)->first();
        if ($approved) {
            $launch = $events->convert($admin, $approved, array_merge($window(18), [
                'name' => $approved->event_name, 'venue' => $approved->venue, 'project_manager_id' => $link('ibrahim.musa@nebostage.com'),
                'production_manager_id' => $crew[0]->id, 'budget_kobo' => 1_250_000_000,
            ]));
            $workflow->transition($admin, $launch, EventStatus::Confirmed, 'Deposit received (demo)');
            foreach ([[1, 'lighting_technician'], [3, 'sound_engineer'], [6, 'camera_operator']] as [$i, $role]) {
                $team->assign($launch, $crew[$i], $role);
            }
        }

        $customer = Customer::firstOrCreate(['email' => 'events@marinatrust.com.ng'], ['name' => 'Kunle Bakare', 'company' => 'Marina Trust Plc', 'phone' => '+2348025573108', 'source' => 'internal']);
        $svc = fn (string ...$slugs) => Service::whereIn('slug', $slugs)->pluck('id')->all();

        $today = $events->create($admin, array_merge($window(0), [
            'name' => 'Marina Trust Town Hall', 'customer_id' => $customer->id, 'event_type' => 'corporate',
            'venue' => 'Marina Trust HQ Auditorium, Marina, Lagos', 'project_manager_id' => $link('tunde.bakare@nebostage.com'), 'budget_kobo' => 380_000_000,
            'production_requirements' => 'Stage 8m x 5m, lectern, two 3x2m LED screens, wireless mics.',
        ]), $svc('stage-staging', 'led-screens-displays', 'sound-audio-production'));
        foreach ([EventStatus::Confirmed, EventStatus::InPreparation, EventStatus::InProgress] as $s) {
            $workflow->transition($admin, $today, $s, 'Confirmed with the client by phone.');
        }
        $team->assign($today, $crew[2], 'general_crew');
        $team->assign($today, $crew[4], 'rigger');
        $team->assign($today, $crew[8], 'stage_manager');

        $next = $events->create($admin, array_merge($window(9, 2), [
            'name' => 'Lagos Jazz Weekend', 'customer_id' => $customer->id, 'event_type' => 'concert',
            'venue' => 'Muri Okunola Park, Lagos', 'project_manager_id' => $link('ibrahim.musa@nebostage.com'), 'budget_kobo' => 2_100_000_000,
            'production_requirements' => 'Outdoor stage 14m x 10m with roof, full rig, delay towers.',
        ]), $svc('stage-staging', 'trussing-rigging', 'event-lighting', 'sound-audio-production', 'barricades'));
        $workflow->transition($admin, $next, EventStatus::Confirmed, 'Contract signed (demo)');
        $team->assign($next, $crew[1], 'lighting_technician');
        $team->assign($next, $crew[5], 'driver');

        auth()->forgetUser();
    }
}
