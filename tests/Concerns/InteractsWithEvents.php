<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Staff;
use App\Models\User;
use App\Services\Events\EventService;

trait InteractsWithEvents
{
    protected function customer(): Customer
    {
        return Customer::firstOrCreate(['email' => 'client@example.com'], ['name' => 'Client Person', 'company' => 'Client Co', 'source' => 'internal']);
    }

    /**
     * An event whose hold window runs from $startDay (days from now) for $days days.
     */
    protected function event(int $startDay = 10, int $days = 1, array $overrides = [], ?User $actor = null): Event
    {
        $actor ??= $this->superAdmin();

        return app(EventService::class)->create($actor, array_merge([
            'name' => 'Test Event '.uniqid(),
            'customer_id' => $this->customer()->id,
            'event_type' => 'corporate',
            'venue' => 'Test Venue, Lagos',
            'setup_starts_at' => now()->addDays($startDay)->setTime(8, 0),
            'starts_at' => now()->addDays($startDay)->setTime(12, 0),
            'ends_at' => now()->addDays($startDay + $days - 1)->setTime(22, 0),
            'breakdown_ends_at' => now()->addDays($startDay + $days)->setTime(3, 0),
        ], $overrides));
    }

    protected function staffMember(string $role = 'rigger', ?User $user = null): Staff
    {
        return Staff::create(['name' => 'Crew '.uniqid(), 'role' => $role, 'user_id' => $user?->id, 'is_active' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function eventPayload(array $overrides = []): array
    {
        $tz = config('nebo.display_timezone');
        $day = now($tz)->addDays(20);

        return array_merge([
            'name' => 'Corporate Dinner',
            'customer_id' => $this->customer()->id,
            'event_type' => 'corporate',
            'venue' => 'Eko Hotel',
            'setup_starts_at' => $day->copy()->setTime(8, 0)->format('Y-m-d\TH:i'),
            'starts_at' => $day->copy()->setTime(18, 0)->format('Y-m-d\TH:i'),
            'ends_at' => $day->copy()->setTime(23, 0)->format('Y-m-d\TH:i'),
            'breakdown_ends_at' => $day->copy()->addDay()->setTime(3, 0)->format('Y-m-d\TH:i'),
            'services' => [],
            'budget' => '2,500,000',
        ], $overrides);
    }
}
