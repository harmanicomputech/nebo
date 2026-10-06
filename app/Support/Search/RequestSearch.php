<?php

namespace App\Support\Search;

use App\Models\EventRequest;
use App\Models\User;
use App\Support\Format;

class RequestSearch implements SearchProvider
{
    public function label(): string
    {
        return 'Requests';
    }

    public function icon(): string
    {
        return 'inbox';
    }

    public function authorize(User $user): bool
    {
        return $user->can('requests.view');
    }

    public function search(string $term, int $limit): array
    {
        return EventRequest::query()->search($term)->latest('id')->limit($limit)->get()
            ->map(fn (EventRequest $r) => [
                'title' => $r->event_name,
                'subtitle' => $r->reference.' · '.($r->company ?: $r->contact_person).' · '.Format::date($r->event_date).' · '.$r->status->label(),
                'url' => route('app.requests.show', $r),
            ])->all();
    }
}
