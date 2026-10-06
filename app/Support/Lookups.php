<?php

namespace App\Support;

use App\Models\Lookup;
use Illuminate\Support\Collection;

/**
 * Read access to option lists, memoised for the current request
 * (bound as a scoped singleton, flushed whenever a lookup is saved).
 *
 * Groups used so far:
 *   condition      meta.blocks_allocation — damaged/critical units can't be allocated
 *   location_type  meta.is_storage — warehouses that hold stock
 *   unit           units of measure for equipment
 */
class Lookups
{
    /** Groups administrators can edit, with a description for the console. */
    public const GROUPS = [
        'condition' => 'Equipment condition grades',
        'location_type' => 'Location types',
        'unit' => 'Units of measure',
        'event_type' => 'Event types',
        'budget_range' => 'Budget ranges',
        'document_category' => 'Document categories',
    ];

    /** @var array<string, Collection<int, Lookup>> */
    private array $memo = [];

    /**
     * @return Collection<int, Lookup>
     */
    public function all(string $group): Collection
    {
        return $this->memo[$group] ??= Lookup::query()->group($group)->get();
    }

    /**
     * Active options as key => label, plus $keep (a value already on a record)
     * so editing an old record never silently drops its value.
     *
     * @return array<string, string>
     */
    public function options(string $group, ?string $keep = null): array
    {
        return $this->all($group)
            ->filter(fn (Lookup $l) => $l->is_active || $l->key === $keep)
            ->mapWithKeys(fn (Lookup $l) => [$l->key => $l->label])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function activeKeys(string $group): array
    {
        return array_keys($this->options($group));
    }

    public function label(string $group, ?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        return $this->all($group)->firstWhere('key', $key)?->label ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Keys in a group whose meta flag is true.
     *
     * @return list<string>
     */
    public function keysWhere(string $group, string $flag): array
    {
        return $this->all($group)->filter(fn (Lookup $l) => (bool) ($l->meta[$flag] ?? false))->pluck('key')->values()->all();
    }

    public function meta(string $group, ?string $key, string $flag, mixed $default = null): mixed
    {
        return $this->all($group)->firstWhere('key', $key)?->meta[$flag] ?? $default;
    }

    public function flush(): void
    {
        $this->memo = [];
    }
}
