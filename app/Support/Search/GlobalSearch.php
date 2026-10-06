<?php

namespace App\Support\Search;

use App\Models\User;

class GlobalSearch
{
    public const MIN_LENGTH = 2;

    /**
     * @return list<class-string<SearchProvider>>
     */
    public static function providers(): array
    {
        return [
            RequestSearch::class,
            EquipmentSearch::class,
            AssetSearch::class,
            UserSearch::class,
            RoleSearch::class,
        ];
    }

    /**
     * Results grouped by type, only from providers the user may see.
     *
     * @return list<array{label: string, icon: string, results: list<array{title: string, subtitle: ?string, url: string}>}>
     */
    public function search(User $user, string $term, int $perGroup = 6): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [];

        foreach (self::providers() as $class) {
            $provider = app($class);

            if (! $provider->authorize($user)) {
                continue;
            }

            $results = $provider->search($term, $perGroup);

            if ($results !== []) {
                $groups[] = ['label' => $provider->label(), 'icon' => $provider->icon(), 'results' => $results];
            }
        }

        return $groups;
    }
}
