<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Services\Allocation\RequirementAnalyzer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds calendar entries for a date range in Lagos time. Each event appears
 * on every day of its hold window, labelled Setup / Show / Breakdown, so the
 * phase is readable without relying on colour. Scheduled maintenance windows
 * appear as Maintenance entries for people who can see them.
 */
class ProductionCalendar
{
    /**
     * @return array{days: list<array{date: CarbonImmutable, entries: list<array<string, mixed>>}>, events: Collection<int, Event>}
     */
    public function build(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = config('nebo.display_timezone');
        $events = Event::query()->visibleTo($user)
            ->whereNotIn('status', ['cancelled'])
            ->overlapping($from->setTimezone('UTC'), $to->setTimezone('UTC'))
            ->with('customer')->orderBy('setup_starts_at')->get();

        $analyzer = app(RequirementAnalyzer::class);
        $open = $events->mapWithKeys(fn (Event $e) => [$e->id => $e->status->holdsResources() && $analyzer->hasOpenRequirements($e)]);

        $jobs = $user->can('viewAny', MaintenanceRecord::class)
            ? MaintenanceRecord::query()->visibleTo($user)->windowOverlapping($from->setTimezone('UTC'), $to->setTimezone('UTC'))
                ->with('asset')->orderBy('scheduled_starts_at')->get()
            : collect();

        $days = [];
        for ($day = $from; $day->lt($to); $day = $day->addDay()) {
            $entries = [];
            foreach ($events as $event) {
                if ($phase = $event->phaseOn($day)) {
                    $entries[] = [
                        'type' => 'event',
                        'phase' => $phase,
                        'event' => $event,
                        'title' => $event->name,
                        'url' => route('app.events.show', $event),
                        'time' => $this->timeFor($event, $phase, $day, $tz),
                        'shortage' => $open[$event->id],
                        'subtitle' => $event->venue.' · '.$event->status->label(),
                    ];
                }
            }
            foreach ($jobs as $job) {
                $start = $job->scheduled_starts_at->setTimezone($tz);
                if ($start->lt($day->addDay()) && $job->scheduled_ends_at->setTimezone($tz)->gt($day)) {
                    $entries[] = [
                        'type' => 'maintenance',
                        'phase' => 'maintenance',
                        'title' => $job->asset->asset_tag.' · '.$job->typeLabel(),
                        'url' => route('app.maintenance.show', $job),
                        'time' => $start->isSameDay($day) ? $start->format('g:ia') : null,
                        'shortage' => false,
                        'subtitle' => $job->issue.' · '.$job->status->label(),
                    ];
                }
            }
            $days[] = ['date' => $day, 'entries' => $entries];
        }

        return ['days' => $days, 'events' => $events];
    }

    private function timeFor(Event $event, string $phase, CarbonImmutable $day, string $tz): ?string
    {
        $at = match ($phase) {
            'setup' => $event->setup_starts_at,
            'show' => $event->starts_at,
            default => null,
        };

        return $at && $at->copy()->setTimezone($tz)->isSameDay($day) ? $at->copy()->setTimezone($tz)->format('g:ia') : null;
    }
}
