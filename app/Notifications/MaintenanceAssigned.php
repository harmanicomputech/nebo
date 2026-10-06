<?php

namespace App\Notifications;

use App\Models\MaintenanceRecord;

class MaintenanceAssigned extends NeboNotification
{
    public function __construct(public MaintenanceRecord $record) {}

    public function title(): string
    {
        return 'Maintenance job assigned to you';
    }

    public function body(): string
    {
        return "{$this->record->reference}: {$this->record->asset->asset_tag} — {$this->record->issue}";
    }

    public function url(): ?string
    {
        return route('app.maintenance.show', $this->record);
    }

    public function icon(): string
    {
        return 'wrench';
    }
}
