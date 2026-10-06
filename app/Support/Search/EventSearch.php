<?php

namespace App\Support\Search;

use App\Models\Event;
use App\Models\User;
use App\Support\Format;

class EventSearch implements SearchProvider
{
    private User $user;

    public function label(): string
    {
        return 'Events';
    }

    public function icon(): string
    {
        return 'calendar-range';
    }

    public function authorize(User $user): bool
    {
        $this->user = $user;

        return $user->can('viewAny', Event::class);
    }

    public function search(string $term, int $limit): array
    {
        return Event::query()->visibleTo($this->user)->search($term)->latest('starts_at')->limit($limit)->get()
            ->map(fn (Event $e) => [
                'title' => $e->name,
                'subtitle' => $e->reference.' · '.Format::date($e->starts_at).' · '.$e->venue.' · '.$e->status->label(),
                'url' => route('app.events.show', $e),
            ])->all();
    }
}
