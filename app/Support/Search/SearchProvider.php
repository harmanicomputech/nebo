<?php

namespace App\Support\Search;

use App\Models\User;

/**
 * One group of global search results (e.g. Equipment, Events). Each module
 * registers its provider in GlobalSearch::providers().
 */
interface SearchProvider
{
    public function label(): string;

    public function icon(): string;

    public function authorize(User $user): bool;

    /**
     * @return list<array{title: string, subtitle: ?string, url: string}>
     */
    public function search(string $term, int $limit): array;
}
