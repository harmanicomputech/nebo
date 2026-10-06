<?php

namespace App\Models;

use App\Models\Concerns\HasNotesAndDocuments;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'company', 'email', 'phone', 'address', 'notes', 'needs_review', 'source'])]
class Customer extends Model
{
    use Auditable, HasNotesAndDocuments, SoftDeletes;

    protected function casts(): array
    {
        return ['needs_review' => 'boolean'];
    }

    /** @return HasMany<EventRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(EventRequest::class);
    }

    public function displayName(): string
    {
        return $this->company ? "{$this->company} ({$this->name})" : $this->name;
    }

    /** @param Builder<Customer> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';
        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('company', 'like', $like)
            ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
    }
}
