<?php

namespace App\Notifications;

class MaintenanceDue extends NeboNotification
{
    public function __construct(public int $dueSoon, public int $overdue) {}

    public function title(): string
    {
        return 'Scheduled maintenance due';
    }

    public function body(): string
    {
        return collect([
            $this->overdue ? "{$this->overdue} overdue" : null,
            $this->dueSoon ? "{$this->dueSoon} due soon" : null,
        ])->filter()->implode(', ').'. Open jobs for them from the maintenance page.';
    }

    public function url(): ?string
    {
        return route('app.maintenance.index', ['view' => 'due']);
    }

    public function level(): string
    {
        return $this->overdue ? 'warning' : 'info';
    }

    public function icon(): string
    {
        return 'wrench';
    }
}
