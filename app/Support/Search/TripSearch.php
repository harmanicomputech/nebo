<?php

namespace App\Support\Search;

use App\Models\LogisticsTrip;
use App\Models\User;
use App\Support\Format;

class TripSearch implements SearchProvider
{
    private User $user;

    public function label(): string
    {
        return 'Trips';
    }

    public function icon(): string
    {
        return 'truck';
    }

    public function authorize(User $user): bool
    {
        $this->user = $user;

        return $user->can('viewAny', LogisticsTrip::class);
    }

    public function search(string $term, int $limit): array
    {
        return LogisticsTrip::query()->visibleTo($this->user)->search($term)->latest('departs_at')->limit($limit)->get()
            ->map(fn (LogisticsTrip $t) => [
                'title' => $t->origin.' → '.$t->destination,
                'subtitle' => $t->reference.' · '.Format::datetime($t->departs_at, 'j M, g:ia').' · '.$t->status->label(),
                'url' => route('app.logistics.trips.show', $t),
            ])->all();
    }
}
