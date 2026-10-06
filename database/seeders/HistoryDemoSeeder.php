<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\LoadStatus;
use App\Enums\RequestStatus;
use App\Enums\TripDirection;
use App\Enums\TripStatus;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Location;
use App\Models\MaintenanceRecord;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Allocation\AllocationService;
use App\Services\Allocation\LoadListService;
use App\Services\Allocation\RequirementService;
use App\Services\Allocation\ReturnService;
use App\Services\Booking\RequestIntake;
use App\Services\Booking\RequestWorkflow;
use App\Services\Commercial\QuotationService;
use App\Services\Events\EventService;
use App\Services\Events\EventWorkflow;
use App\Services\Events\TeamService;
use App\Services\Logistics\TripService;
use App\Services\Maintenance\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * A year of demo business history (never production): about two dozen past
 * productions from request to check-in, lost and cancelled deals, damage and
 * repairs, and a pipeline of future work. Everything goes through the real
 * services with the clock set to the moment each step happened, so the
 * ledger, statuses, timelines, references and reports are all consistent.
 *
 * Runs after the reference, users, inventory and current-events demo
 * seeders and before the "current state" demo seeders, so every unit it uses
 * is back in the warehouse before today's demo bookings are made.
 */
class HistoryDemoSeeder extends Seeder
{
    /** Day rates (₦) used to price the quotations. */
    private const RATES = ['NS-ML-01' => 45000, 'NS-MW-02' => 35000, 'NS-S4-03' => 8000, 'NS-FS-04' => 25000, 'NS-GMA-05' => 150000, 'NS-CL-06' => 120000,
        'NS-HZ-07' => 15000, 'NS-DIS-08' => 30000, 'NS-DIM-09' => 40000, 'NS-HST-10' => 20000, 'NS-COM-11' => 12000,
        'NS-BLK-01' => 6000, 'NS-BLK-02' => 1500, 'NS-BLK-03' => 1000, 'NS-BLK-04' => 1000, 'NS-BLK-05' => 500, 'NS-BLK-06' => 2000];

    /** Kit per production size: [serialized SKU => units], [bulk SKU => quantity], crew, management fee (₦). */
    private const KITS = [
        'small' => [['NS-S4-03' => 8, 'NS-CL-06' => 1, 'NS-COM-11' => 4], ['NS-BLK-02' => 20, 'NS-BLK-04' => 24], 4, 350000],
        'medium' => [['NS-ML-01' => 8, 'NS-MW-02' => 6, 'NS-GMA-05' => 1, 'NS-CL-06' => 1, 'NS-HST-10' => 4, 'NS-HZ-07' => 1], ['NS-BLK-01' => 24, 'NS-BLK-02' => 60, 'NS-BLK-03' => 40, 'NS-BLK-05' => 16], 8, 650000],
        'large' => [['NS-ML-01' => 16, 'NS-MW-02' => 10, 'NS-S4-03' => 12, 'NS-FS-04' => 2, 'NS-GMA-05' => 1, 'NS-CL-06' => 2, 'NS-HST-10' => 8, 'NS-DIS-08' => 2, 'NS-DIM-09' => 1, 'NS-COM-11' => 8],
            ['NS-BLK-01' => 60, 'NS-BLK-02' => 120, 'NS-BLK-03' => 80, 'NS-BLK-04' => 60, 'NS-BLK-05' => 40, 'NS-BLK-06' => 12], 14, 1200000],
    ];

    /** key => [company, contact, email, phone, type, city, state] */
    private const CUSTOMERS = [
        'zenith' => ['Crestview Holdings', 'Funke Adebayo', 'funke.adebayo@crestviewholdings.com.ng', '08034417256', 'corporate', 'Lagos', 'Lagos'],
        'grace' => ['Grace Assembly', 'Pastor Daniel Okon', 'events@graceassemblyabuja.org', '08097315520', 'religious', 'Abuja', 'FCT'],
        'volt' => ['Volt Electronics', 'Ngozi Eze', 'ngozi.eze@voltelectronics.ng', '08152084417', 'corporate', 'Lagos', 'Lagos'],
        'firstcorp' => ['Marina Trust Plc', 'Kunle Bakare', 'events@marinatrust.com.ng', '08025573108', 'corporate', 'Lagos', 'Lagos'],
        'tourism' => ['Lagos Tourism Board', 'Adaeze Nwankwo', 'events@lagostourismboard.ng', '08061112222', 'government', 'Lagos', 'Lagos'],
        'afrolive' => ['Afrobeats Live', 'Seun Ajayi', 'bookings@afrobeatslive.ng', '08074445555', 'entertainment', 'Lagos', 'Lagos'],
        'unibadan' => ['University of Ibadan Alumni Office', 'Dr. Kemi Ogunleye', 'events.office@ui.edu.ng', '08035556666', 'education', 'Ibadan', 'Oyo'],
        'kanofair' => ['Kano Trade Fair Ltd', 'Aminu Sani', 'expo@kanotradefair.ng', '08067778888', 'agency', 'Kano', 'Kano'],
        'carnival' => ['Calabar Carnival Committee', 'Effiong Bassey', 'committee@calabarcarnival.ng', '08029990000', 'government', 'Calabar', 'Cross River'],
        'horizon' => ['Horizon Telecom', 'Yetunde Bello', 'brand@horizontelecom.ng', '08091231234', 'corporate', 'Lagos', 'Lagos'],
        'energy' => ['Rivers Energy Summit', 'Tonye Harry', 'summit@riversenergysummit.ng', '08033214321', 'corporate', 'Port Harcourt', 'Rivers'],
        'nkemtolu' => [null, 'Nkem Okafor', 'nkemtolu.okafor@gmail.com', '07031239876', 'individual', 'Enugu', 'Enugu'],
    ];

    /**
     * Completed productions: [days ago, name, type, customer, venue, days, size, services, damage?].
     * Spaced so no two hold windows overlap.
     */
    private const PAST = [
        [330, 'Horizon Telecom Dealers Conference', 'conference', 'horizon', 'Eko Convention Centre, Victoria Island, Lagos', 2, 'large', ['stage-staging', 'event-lighting', 'led-screens-displays', 'sound-audio-production'], false],
        [316, 'Grace Assembly Watch Night Service', 'church', 'grace', 'Grace Assembly Auditorium, Wuse, Abuja', 1, 'medium', ['event-lighting', 'sound-audio-production', 'livestreaming'], false],
        [301, 'Afrobeats Live: Island Sessions', 'concert', 'afrolive', 'Tafawa Balewa Square, Lagos', 1, 'large', ['stage-staging', 'trussing-rigging', 'event-lighting', 'sound-audio-production'], true],
        [289, 'Crestview Leadership Retreat', 'corporate', 'zenith', 'Lagos Continental Hotel, Lagos', 1, 'small', ['sound-audio-production', 'led-screens-displays'], false],
        [272, 'Kano International Trade Fair Opening', 'festival', 'kanofair', 'Kano Trade Fair Complex, Kano', 2, 'large', ['stage-staging', 'barricades', 'sound-audio-production', 'event-lighting'], false],
        [259, 'Nkem & Tolu Wedding Reception', 'wedding', 'nkemtolu', 'Nike Lake Resort, Enugu', 1, 'small', ['event-lighting', 'photography-videography'], false],
        [246, 'University of Ibadan Convocation Lecture', 'conference', 'unibadan', 'Trenchard Hall, University of Ibadan', 1, 'medium', ['sound-audio-production', 'livestreaming'], false],
        [233, 'Volt Partner Awards', 'awards', 'volt', 'Federal Palace Hotel, Lagos', 1, 'medium', ['stage-staging', 'event-lighting', 'led-screens-displays'], true],
        [221, 'Lagos Tourism Easter Fiesta', 'festival', 'tourism', 'Lekki Leisure Lake, Lagos', 2, 'large', ['stage-staging', 'barricades', 'sound-audio-production', 'event-lighting'], false],
        [205, 'Marina Trust Annual General Meeting', 'corporate', 'firstcorp', 'Civic Centre, Victoria Island, Lagos', 1, 'medium', ['sound-audio-production', 'led-screens-displays', 'livestreaming'], false],
        [192, 'Rivers Energy Summit', 'conference', 'energy', 'Hotel Presidential, Port Harcourt', 2, 'large', ['full-event-production'], false],
        [178, 'Grace Assembly Youth Convention', 'church', 'grace', 'Old Parade Ground, Abuja', 2, 'large', ['stage-staging', 'event-lighting', 'sound-audio-production', 'livestreaming'], true],
        [166, 'Horizon Telecom 5G Launch', 'product_launch', 'horizon', 'Landmark Event Centre, Lagos', 1, 'large', ['full-event-production'], false],
        [151, 'Crestview Mid-Year Town Hall', 'corporate', 'zenith', 'Crestview House, Ikoyi, Lagos', 1, 'small', ['sound-audio-production'], false],
        [139, 'Afrobeats Live: Abuja Edition', 'concert', 'afrolive', 'Eagle Square, Abuja', 1, 'large', ['stage-staging', 'trussing-rigging', 'event-lighting', 'sound-audio-production'], false],
        [124, 'University of Ibadan Alumni Gala', 'awards', 'unibadan', 'Kakanfo Inn, Ibadan', 1, 'medium', ['event-lighting', 'sound-audio-production'], false],
        [110, 'Lagos Tourism Independence Concert', 'concert', 'tourism', 'Tafawa Balewa Square, Lagos', 1, 'large', ['stage-staging', 'barricades', 'event-lighting', 'sound-audio-production'], true],
        [97, 'Volt Phone 9 Media Preview', 'product_launch', 'volt', 'The Wheatbaker, Ikoyi, Lagos', 1, 'medium', ['event-lighting', 'led-screens-displays'], false],
        [84, 'Kano Trade Fair Business Forum', 'conference', 'kanofair', 'Bristol Palace Hotel, Kano', 1, 'medium', ['sound-audio-production', 'led-screens-displays'], false],
        [70, 'Marina Trust Customer Day', 'corporate', 'firstcorp', 'Muri Okunola Park, Lagos', 1, 'medium', ['stage-staging', 'sound-audio-production'], false],
        [57, 'Calabar Carnival Street Party', 'festival', 'carnival', 'U.J. Esuene Stadium, Calabar', 2, 'large', ['stage-staging', 'barricades', 'event-lighting', 'sound-audio-production'], false],
        [44, 'Grace Assembly Thanksgiving Service', 'church', 'grace', 'Grace Assembly Auditorium, Wuse, Abuja', 1, 'medium', ['event-lighting', 'sound-audio-production', 'livestreaming'], false],
        [30, 'Horizon Telecom Retail Awards', 'awards', 'horizon', 'Eko Hotel, Victoria Island, Lagos', 1, 'medium', ['stage-staging', 'event-lighting', 'led-screens-displays'], false],
        [14, 'Rivers Energy Investors Dinner', 'corporate', 'energy', 'Novotel, Port Harcourt', 1, 'small', ['event-lighting', 'sound-audio-production'], false],
    ];

    private User $admin;

    private User $pm;

    private User $finance;

    private Location $warehouse;

    private string $base;

    /** @var array<string, Staff> */
    private array $staff;

    /** @var array<string, Vehicle> */
    private array $fleet;

    private int $seq = 0;

    public function __construct(
        private RequestIntake $intake,
        private RequestWorkflow $requests,
        private QuotationService $quotes,
        private EventService $events,
        private EventWorkflow $workflow,
        private TeamService $team,
        private RequirementService $requirements,
        private AllocationService $allocations,
        private LoadListService $loadLists,
        private TripService $trips,
        private ReturnService $returns,
        private MaintenanceService $maintenance,
    ) {}

    /** Productions seeded per batch (the web installer runs one batch per request). */
    public const BATCH_SIZE = 3;

    public static function batches(): int
    {
        return (int) ceil(count(self::PAST) / self::BATCH_SIZE) + 1;
    }

    public function run(): void
    {
        for ($batch = 0; $batch < self::batches(); $batch++) {
            $this->runBatch($batch);
        }
    }

    /**
     * One slice of the history: a batch of past productions, or (the last
     * batch) the lost deals, the live pipeline and customer details. Each
     * batch skips itself when its data already exists, so it can be retried.
     */
    public function runBatch(int $batch): void
    {
        $productions = array_slice(self::PAST, $batch * self::BATCH_SIZE, self::BATCH_SIZE, true);
        $marker = $productions ? end($productions)[1] : 'Horizon Telecom End of Year Party';
        if (Event::where('name', $marker)->exists()) {
            return;
        }

        Notification::fake(); // a year of history shouldn't fill everyone's bell
        $now = CarbonImmutable::now(config('nebo.display_timezone'))->startOfHour();

        $this->admin = User::where('email', 'ada.okafor@nebostage.com')->firstOrFail();
        $this->pm = User::where('email', 'ibrahim.musa@nebostage.com')->first() ?? $this->admin;
        $this->finance = User::where('email', 'funmi.adeyemi@nebostage.com')->first() ?? $this->admin;
        $this->warehouse = Location::where('code', 'MAIN')->firstOrFail();
        $this->base = trim($this->warehouse->name.($this->warehouse->address ? ', '.$this->warehouse->address : ''));
        $this->staff = Staff::query()->get()->keyBy('role')->all();
        $this->fleet = LogisticsDemoSeeder::fleet($this->staff['driver'] ?? null, $this->warehouse);
        $this->seq = $batch * self::BATCH_SIZE;
        auth()->login($this->admin);

        try {
            foreach ($productions as $i => [$ago, $name, $type, $customer, $venue, $days, $size, $services, $damage]) {
                $this->production($now->subDays($ago)->setTime(18, 0), $name, $type, $customer, $venue, $days, $size, $services, $damage, $i);
            }

            if (! $productions) {
                $this->lostDeals($now);
                $this->pipeline($now);
            }
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }

        if (! $productions) {
            foreach (self::CUSTOMERS as [$company, $contact, $email, , $type, $city, $state]) {
                Customer::where('email', $email)->update(['type' => $type, 'city' => $city, 'state' => $state]);
            }
        }

        auth()->logout();
    }

    /** One production from first enquiry to check-in. */
    private function production(CarbonImmutable $show, string $name, string $type, string $customerKey, string $venue, int $days, string $size, array $services, bool $damage, int $i): void
    {
        $setup = $show->subDay()->setTime(9, 0);
        $breakdown = $show->addDays($days - 1)->setTime(23, 30);
        $request = $this->enquiry($setup->subDays(40 + $i % 9), $name, $type, $customerKey, $venue, $show, $days, $services);

        $this->at(self::i($request->created_at)->addDay());
        $this->requests->assign($this->admin, $request, $this->pm);
        $this->requests->transition($this->pm, $request->fresh(), RequestStatus::UnderReview, 'Reviewed the brief');
        $this->at(self::i($request->created_at)->addDays(3));
        $this->requests->transition($this->pm, $request->fresh(), RequestStatus::QuotationPreparation, 'Site visit done; pricing the production');

        $quote = $this->quote($request, $size, $days, self::i($request->created_at)->addDays(5), self::i($request->created_at)->addDays(8 + $i % 4));

        $this->at(self::i($quote->responded_at)->addDay());
        $this->requests->transition($this->pm, $request->fresh(), RequestStatus::Approved, 'Deposit received');
        $event = $this->events->convert($this->pm, $request->fresh(), [
            'name' => $name, 'venue' => $venue,
            'setup_starts_at' => $setup->utc(), 'starts_at' => $show->utc(), 'ends_at' => $show->addDays($days - 1)->setTime(22, 0)->utc(), 'breakdown_ends_at' => $breakdown->utc(),
            'project_manager_id' => $this->pm->id, 'production_manager_id' => $this->staff['production_manager']?->id,
            'budget_kobo' => $quote->total_kobo,
        ]);
        $quote->update(['event_id' => $event->id]);
        $this->workflow->transition($this->pm, $event, EventStatus::Confirmed);
        foreach (['production_manager', 'lighting_technician', 'sound_engineer', 'rigger', 'general_crew', 'stage_manager'] as $role) {
            if (isset($this->staff[$role]) && ($size !== 'small' || in_array($role, ['production_manager', 'sound_engineer', 'general_crew'], true))) {
                $this->team->assign($event, $this->staff[$role], $role);
            }
        }

        // Kit, a week before.
        $this->at($setup->subDays(7));
        [$units, $bulk] = self::KITS[$size];
        foreach ($units + $bulk as $sku => $qty) {
            $item = Equipment::where('sku', $sku)->firstOrFail();
            $this->requirements->set($event, $item, $qty);
            $item->isSerialized() ? $this->allocations->autoReserve($this->pm, $event, $item, $qty) : $this->allocations->reserveBulk($this->pm, $event, $item, $qty, $this->warehouse->id);
        }

        // Load-out the day before setup.
        $this->at($setup->subDays(2));
        $this->workflow->transition($this->pm, $event->fresh(), EventStatus::InPreparation);
        $this->at($setup->subDay()->setTime(14, 0));
        $list = $this->loadLists->sync($this->pm, $event->fresh());
        $this->loadLists->advanceAll($this->pm, $list, LoadStatus::Checked);
        $this->loadLists->dispatch($this->pm, $list->fresh());

        // Out to the venue.
        $outstation = ! str_contains($venue, 'Lagos');
        $vehicle = $size === 'small' ? $this->fleet['van'] : $this->fleet['truck'];
        $driveHours = $outstation ? 9 : 2;
        $out = $this->trips->plan($this->pm, $event->fresh(), ['direction' => 'outbound', 'vehicle_id' => $vehicle->id, 'driver_id' => $this->staff['driver']?->id,
            'origin' => $this->base, 'destination' => $venue, 'departs_at' => $setup->subHours($driveHours)->utc(), 'arrives_at' => $setup->utc(),
            'crew' => array_values(array_filter([$this->staff['general_crew']?->id])), 'items' => $this->trips->manifestCandidates($event, TripDirection::Outbound)->modelKeys()]);
        $this->at($setup->subHours($driveHours));
        $this->trips->transition($this->pm, $out, TripStatus::InTransit);
        $this->at($setup->addMinutes($i % 5 === 0 ? 75 : 10)); // a few arrive late
        $this->trips->transition($this->pm, $out->fresh(), TripStatus::Arrived, null, 'Venue manager');

        // Show.
        $this->at($show->subHours(2));
        $this->workflow->transition($this->pm, $event->fresh(), EventStatus::InProgress);

        // Back to base and check-in.
        $back = $this->trips->plan($this->pm, $event->fresh(), ['direction' => 'return', 'vehicle_id' => $vehicle->id, 'driver_id' => $this->staff['driver']?->id,
            'origin' => $venue, 'destination' => $this->base, 'departs_at' => $breakdown->utc(), 'arrives_at' => $breakdown->addHours($driveHours)->utc(),
            'items' => $this->trips->manifestCandidates($event, TripDirection::Return)->modelKeys()]);
        $this->at($breakdown);
        $this->trips->transition($this->pm, $back, TripStatus::InTransit);
        $this->at($breakdown->addHours($driveHours));
        $this->trips->transition($this->pm, $back->fresh(), TripStatus::Arrived, null, 'Warehouse team');

        $this->at($breakdown->addHours($driveHours + 1));
        $lines = [];
        $damagedDone = false;
        foreach ($this->returns->outstanding($event->fresh()) as $allocation) {
            if ($allocation->asset_id) {
                $broken = $damage && ! $damagedDone && $allocation->equipment->sku === 'NS-ML-01';
                $damagedDone = $damagedDone || $broken;
                $lines[$allocation->id] = ['include' => 1, 'outcome' => $broken ? 'damaged' : 'returned', 'note' => $broken ? 'Dropped during breakdown; lens cracked' : null];
            } else {
                $missing = $damage && $allocation->equipment->sku === 'NS-BLK-02' ? 2 : 0;
                $lines[$allocation->id] = ['include' => 1, 'returned' => $allocation->quantity - $missing, 'missing' => $missing, 'damaged' => 0];
            }
        }
        $this->returns->process($this->pm, $event->fresh(), $lines, $this->warehouse->id, 'Checked in by the warehouse team');

        $this->at($breakdown->addHours($driveHours + 2));
        $this->workflow->transition($this->pm, $event->fresh(), EventStatus::Completed);
        $this->requests->transition($this->pm, $request->fresh(), RequestStatus::Completed, 'Production delivered');

        // Repair whatever came back damaged.
        foreach (MaintenanceRecord::where('event_id', $event->id)->open()->get() as $job) {
            $this->at($breakdown->addDays(2)->setTime(10, 0));
            $this->maintenance->start($this->admin, $job);
            $this->at($breakdown->addDays(4 + $i % 3)->setTime(15, 0));
            $this->maintenance->complete($this->admin, $job->fresh(), ['work_done' => 'Replaced front lens and checked the optics; tested for 2 hours.', 'outcome_condition' => 'good',
                'cost_kobo' => (85000 + 5000 * ($i % 4)) * 100, 'parts_used' => '1 × front lens assembly']);
        }
    }

    /** Requests that didn't become business. */
    private function lostDeals(CarbonImmutable $now): void
    {
        // Quoted, then the client chose another supplier.
        foreach ([[300, 'Lekki Food & Music Festival', 'festival', 'tourism', 'Lekki Leisure Lake, Lagos'], [180, 'Horizon Telecom Staff Party', 'corporate', 'horizon', 'Federal Palace Hotel, Lagos'], [75, 'Ibadan Tech Week', 'conference', 'unibadan', 'International Conference Centre, Ibadan']] as $j => [$ago, $name, $type, $customer, $venue]) {
            $request = $this->enquiry($now->subDays($ago + 35), $name, $type, $customer, $venue, $now->subDays($ago), 1, ['stage-staging', 'sound-audio-production']);
            $this->toQuotation($request);
            $quote = $this->quote($request, 'medium', 1, self::i($request->created_at)->addDays(5), null);
            $this->at(self::i($quote->sent_at)->addDays(6));
            $this->quotes->respond(null, $quote->fresh(), false, self::CUSTOMERS[$customer][1], 'We went with a supplier closer to the venue.');
            $this->requests->transition($this->pm, $request->fresh(), RequestStatus::Declined, 'Client chose another supplier');
        }

        // Quoted, then no answer: the quotation expired.
        foreach ([[240, 'Kano Durbar Cultural Night', 'festival', 'kanofair', 'Emir’s Palace Grounds, Kano'], [120, 'Calabar Christmas Village Opening', 'festival', 'carnival', 'Calabar Christmas Village']] as [$ago, $name, $type, $customer, $venue]) {
            $request = $this->enquiry($now->subDays($ago + 30), $name, $type, $customer, $venue, $now->subDays($ago), 1, ['event-lighting', 'sound-audio-production']);
            $this->toQuotation($request);
            $quote = $this->quote($request, 'large', 2, self::i($request->created_at)->addDays(4), null);
            $this->at(self::i($quote->valid_until)->addDays(1)->setTime(1, 0));
            $this->quotes->expireOverdue();
            $this->requests->transition($this->pm, $request->fresh(), RequestStatus::AwaitingCustomer, 'Quotation expired; chased by phone');
            $this->requests->transition($this->pm, $request->fresh(), RequestStatus::Cancelled, 'No response from the client');
        }

        // Cancelled before any quotation.
        foreach ([[200, 'Okafor 60th Birthday', 'other', 'nkemtolu', 'Golden Royale, Enugu'], [60, 'Grace Assembly Outreach Rally', 'church', 'grace', 'Area 1 Roundabout Grounds, Abuja']] as [$ago, $name, $type, $customer, $venue]) {
            $request = $this->enquiry($now->subDays($ago + 25), $name, $type, $customer, $venue, $now->subDays($ago), 1, ['sound-audio-production']);
            $this->at(self::i($request->created_at)->addDays(2));
            $this->requests->transition($this->pm, $request->fresh(), RequestStatus::Contacted, 'Called the client');
            $this->at(self::i($request->created_at)->addDays(9));
            $this->requests->transition($this->pm, $request->fresh(), RequestStatus::Cancelled, 'Budget not approved this year');
        }

        // Won, scheduled, then called off by the client before load-out.
        $request = $this->enquiry($now->subDays(150), 'Marina Trust Sports Day', 'corporate', 'firstcorp', 'Teslim Balogun Stadium, Lagos', $now->subDays(105), 1, ['stage-staging', 'sound-audio-production', 'barricades']);
        $this->toQuotation($request);
        $quote = $this->quote($request, 'medium', 1, self::i($request->created_at)->addDays(4), self::i($request->created_at)->addDays(7));
        $this->at(self::i($quote->responded_at)->addDay());
        $this->requests->transition($this->pm, $request->fresh(), RequestStatus::Approved, 'Deposit received');
        $show = $now->subDays(105)->setTime(10, 0);
        $event = $this->events->convert($this->pm, $request->fresh(), ['name' => 'Marina Trust Sports Day', 'venue' => 'Teslim Balogun Stadium, Lagos',
            'setup_starts_at' => $show->subDay()->utc(), 'starts_at' => $show->utc(), 'ends_at' => $show->setTime(18, 0)->utc(), 'breakdown_ends_at' => $show->setTime(22, 0)->utc(),
            'project_manager_id' => $this->pm->id]);
        $this->workflow->transition($this->pm, $event, EventStatus::Confirmed);
        $this->at($show->subDays(10));
        $item = Equipment::where('sku', 'NS-CL-06')->firstOrFail();
        $this->requirements->set($event, $item, 1);
        $this->allocations->autoReserve($this->pm, $event, $item, 1);
        $this->at($show->subDays(6));
        $this->workflow->transition($this->pm, $event->fresh(), EventStatus::Cancelled, 'Client postponed the sports day indefinitely');
        $this->requests->transition($this->pm, $request->fresh(), RequestStatus::Cancelled, 'Event postponed by the client');
    }

    /** Work in the pipeline: quoted, confirmed and brand-new requests. */
    private function pipeline(CarbonImmutable $now): void
    {
        // Confirmed for next month, kit reserved.
        $show = $now->addDays(42)->setTime(18, 0);
        $request = $this->enquiry($now->subDays(20), 'Horizon Telecom End of Year Party', 'corporate', 'horizon', 'Eko Hotel, Victoria Island, Lagos', $show, 1, ['stage-staging', 'event-lighting', 'sound-audio-production']);
        $this->toQuotation($request);
        $quote = $this->quote($request, 'medium', 1, self::i($request->created_at)->addDays(4), self::i($request->created_at)->addDays(9));
        $this->at(self::i($quote->responded_at)->addDay());
        $this->requests->transition($this->pm, $request->fresh(), RequestStatus::Approved, 'Deposit received');
        $event = $this->events->convert($this->pm, $request->fresh(), ['name' => 'Horizon Telecom End of Year Party', 'venue' => 'Eko Hotel, Victoria Island, Lagos',
            'setup_starts_at' => $show->subDay()->setTime(9, 0)->utc(), 'starts_at' => $show->utc(), 'ends_at' => $show->setTime(23, 0)->utc(), 'breakdown_ends_at' => $show->addDay()->setTime(4, 0)->utc(),
            'project_manager_id' => $this->pm->id, 'production_manager_id' => $this->staff['production_manager']?->id, 'budget_kobo' => $quote->total_kobo]);
        $quote->update(['event_id' => $event->id]);
        $this->workflow->transition($this->pm, $event, EventStatus::Confirmed);
        $this->at(CarbonImmutable::now(config('nebo.display_timezone'))->addDay()->min($now->subDay()));
        foreach (['NS-MW-02' => 6, 'NS-S4-03' => 8] as $sku => $qty) {
            $item = Equipment::where('sku', $sku)->firstOrFail();
            $this->requirements->set($event, $item, $qty);
            $this->allocations->autoReserve($this->pm, $event, $item, $qty);
        }
        $this->requirements->set($event, Equipment::where('sku', 'NS-BLK-02')->firstOrFail(), 40);

        // Quotation out, waiting for the client.
        $request = $this->enquiry($now->subDays(9), 'Kano Trade Fair 2027 Launch', 'festival', 'kanofair', 'Kano Trade Fair Complex, Kano', $now->addDays(75), 2, ['stage-staging', 'barricades', 'sound-audio-production', 'event-lighting']);
        $this->toQuotation($request);
        $this->quote($request, 'large', 2, $now->subDays(3), null);

        // Brand-new enquiries.
        $this->enquiry($now->subDays(2), 'University of Ibadan Founders Day', 'conference', 'unibadan', 'Trenchard Hall, University of Ibadan', $now->addDays(60), 1, ['sound-audio-production', 'livestreaming']);
        $this->enquiry($now->subHours(5), 'Calabar Carnival 2026 Main Stage', 'festival', 'carnival', 'Calabar Carnival Route, Calabar', $now->addDays(80), 3, ['stage-staging', 'trussing-rigging', 'event-lighting', 'sound-audio-production', 'barricades']);
    }

    private function enquiry(CarbonImmutable $at, string $name, string $type, string $customerKey, string $venue, CarbonImmutable $date, int $days, array $services): EventRequest
    {
        $this->at($at->setTime(10 + $this->seq % 7, 15));
        [$company, $contact, $email, $phone] = self::CUSTOMERS[$customerKey];
        $budget = ['under_1m', '1m_3m', '3m_5m', '5m_10m', '10m_plus', 'discuss'][$this->seq++ % 6];

        return $this->intake->submit([
            'event_name' => $name, 'event_type' => $type, 'event_date' => $date->toDateString(), 'venue' => $venue,
            'contact_person' => $contact, 'company' => $company, 'email' => $email, 'phone' => $phone,
            'services' => Service::whereIn('slug', $services)->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'duration_days' => $days, 'setup_at' => $date->subDay()->setTime(9, 0)->utc()->toDateTimeString(),
            'has_existing_design' => false, 'budget_range' => $budget, 'submission_key' => (string) Str::uuid(),
            'requirements' => 'Stage, lighting and sound for the audience size discussed on the phone; venue power to be confirmed on the site visit.',
        ]);
    }

    private function toQuotation(EventRequest $request): void
    {
        $this->at(self::i($request->created_at)->addDay());
        $this->requests->assign($this->admin, $request, $this->pm);
        $this->requests->transition($this->pm, $request->fresh(), RequestStatus::UnderReview, 'Reviewed the brief');
        $this->requests->transition($this->pm, $request->fresh(), RequestStatus::QuotationPreparation, 'Pricing the production');
    }

    /** Prices the kit and sends it; accepts it on $acceptedAt when given. */
    private function quote(EventRequest $request, string $size, int $days, CarbonImmutable $sentAt, ?CarbonImmutable $acceptedAt): Quotation
    {
        [$units, $bulk, $crew, $fee] = self::KITS[$size];
        $lines = [['section' => 'services', 'description' => 'Technical production management and show calling', 'quantity' => 1, 'days' => 1, 'unit_price_kobo' => $fee * 100]];
        foreach ($units + $bulk as $sku => $qty) {
            $item = Equipment::where('sku', $sku)->first();
            $lines[] = ['section' => 'equipment', 'description' => $item->name, 'equipment_id' => $item->id, 'quantity' => $qty, 'days' => $days, 'unit_price_kobo' => self::RATES[$sku] * 100];
        }
        $lines[] = ['section' => 'labour', 'description' => 'Technical crew', 'quantity' => $crew, 'days' => $days + 1, 'unit_price_kobo' => 35000_00];
        $lines[] = ['section' => 'transport', 'description' => str_contains($request->venue, 'Lagos') ? 'Haulage within Lagos, return' : 'Haulage to '.trim(strrchr($request->venue, ',') ?: $request->venue, ', ').' and back', 'quantity' => 1, 'days' => 1,
            'unit_price_kobo' => (str_contains($request->venue, 'Lagos') ? 250000 : 900000) * 100];

        $this->at($sentAt->setTime(11, 0));
        $quote = $this->quotes->create($this->finance, $request->customer, [
            'title' => $request->event_name, 'event_request_id' => $request->id, 'valid_until' => $sentAt->addDays(14)->toDateString(),
            'discount_kobo' => $size === 'large' ? 250000_00 : 0, 'items' => $lines,
        ]);
        $this->quotes->send($this->finance, $quote->fresh());

        if ($acceptedAt) {
            $this->at($acceptedAt->setTime(15, 30));
            $this->quotes->markViewed($quote->fresh());
            $this->quotes->respond(null, $quote->fresh(), true, $request->contact_person, 'Approved. Purchase order to follow.');
        }

        return $quote->fresh();
    }

    /** Immutable copy of a stored timestamp (Eloquent dates are mutable). */
    private static function i(\DateTimeInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone(config('nebo.display_timezone'));
    }

    private function at(\DateTimeInterface $moment): void
    {
        $at = CarbonImmutable::instance($moment);
        Carbon::setTestNow($at);
        CarbonImmutable::setTestNow($at);
    }
}
