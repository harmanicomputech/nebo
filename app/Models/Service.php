<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A production service Nebo Stage offers. Managed in the console, not code. */
#[Fillable(['name', 'slug', 'description', 'icon', 'sort_order', 'is_public', 'is_active'])]
class Service extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return BelongsToMany<EventRequest, $this> */
    public function requests(): BelongsToMany
    {
        return $this->belongsToMany(EventRequest::class);
    }

    /** @param Builder<Service> $query */
    public function scopeOffered(Builder $query): void
    {
        $query->where('is_active', true)->where('is_public', true)->orderBy('sort_order')->orderBy('name');
    }
}
