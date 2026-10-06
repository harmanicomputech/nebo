<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceSchedule;
use App\Notifications\MaintenanceDue;
use App\Support\Recipients;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Daily digest of schedules falling due (D52). Each schedule is mentioned
 * once when it enters the reminder window, then weekly while overdue;
 * schedules with an open job are skipped.
 *
 * @return array{due_soon: int, overdue: int}
 */
class MaintenanceReminders
{
    public function send(): array
    {
        $today = CarbonImmutable::now(config('nebo.display_timezone'))->startOfDay();
        $horizon = $today->addDays((int) Settings::get('maintenance.reminder_days'));

        $schedules = MaintenanceSchedule::query()->dueBy($horizon->toDateString())
            ->whereDoesntHave('records', fn ($q) => $q->open())
            ->where(fn ($q) => $q->whereNull('last_reminded_on')
                ->orWhere(fn ($o) => $o->whereDate('next_due_on', '<', $today->toDateString())->whereDate('last_reminded_on', '<=', $today->subDays(7)->toDateString())))
            ->get();

        if ($schedules->isEmpty()) {
            return ['due_soon' => 0, 'overdue' => 0];
        }

        $overdue = $schedules->filter(fn (MaintenanceSchedule $s) => $s->next_due_on->lt($today))->count();
        $dueSoon = $schedules->count() - $overdue;

        $recipients = Recipients::withPermission('maintenance.manage');
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new MaintenanceDue($dueSoon, $overdue));
        }

        MaintenanceSchedule::whereKey($schedules->modelKeys())->update(['last_reminded_on' => $today->toDateString()]);

        return ['due_soon' => $dueSoon, 'overdue' => $overdue];
    }
}
