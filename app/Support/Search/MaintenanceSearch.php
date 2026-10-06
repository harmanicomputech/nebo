<?php

namespace App\Support\Search;

use App\Models\MaintenanceRecord;
use App\Models\User;

class MaintenanceSearch implements SearchProvider
{
    private User $user;

    public function label(): string
    {
        return 'Maintenance';
    }

    public function icon(): string
    {
        return 'wrench';
    }

    public function authorize(User $user): bool
    {
        $this->user = $user;

        return $user->can('viewAny', MaintenanceRecord::class);
    }

    public function search(string $term, int $limit): array
    {
        return MaintenanceRecord::query()->visibleTo($this->user)->search($term)->with('asset')->latest()->limit($limit)->get()
            ->map(fn (MaintenanceRecord $r) => [
                'title' => $r->asset->asset_tag.' · '.$r->issue,
                'subtitle' => $r->reference.' · '.$r->typeLabel().' · '.$r->status->label(),
                'url' => route('app.maintenance.show', $r),
            ])->all();
    }
}
