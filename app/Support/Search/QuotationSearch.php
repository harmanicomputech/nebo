<?php

namespace App\Support\Search;

use App\Models\Quotation;
use App\Models\User;
use App\Support\Format;

class QuotationSearch implements SearchProvider
{
    public function label(): string
    {
        return 'Quotations';
    }

    public function icon(): string
    {
        return 'receipt';
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', Quotation::class);
    }

    public function search(string $term, int $limit): array
    {
        return Quotation::query()->search($term)->with('customer')->latest('id')->limit($limit)->get()
            ->map(fn (Quotation $q) => [
                'title' => $q->title,
                'subtitle' => $q->label().' · '.$q->customer->displayName().' · '.Format::naira($q->total_kobo).' · '.$q->status->label(),
                'url' => route('app.quotations.show', $q),
            ])->all();
    }
}
