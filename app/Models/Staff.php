<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Someone who works events; optionally linked to a login account (D5). */
#[Fillable(['user_id', 'name', 'role', 'phone', 'email', 'notes', 'is_active'])]
class Staff extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'staff';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return HasMany<EventStaff, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(EventStaff::class);
    }

    public function roleLabel(): string
    {
        return app(Lookups::class)->label('staff_role', $this->role);
    }

    /** @param Builder<Staff> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Staff> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';
        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like));
    }

    /**
     * @return array<int, string>
     */
    public static function options(?int $keep = null): array
    {
        return self::query()->where(fn ($q) => $q->where('is_active', true)->when($keep, fn ($q) => $q->orWhere('id', $keep)))
            ->orderBy('name')->get()->mapWithKeys(fn (self $s) => [$s->id => "{$s->name} · {$s->roleLabel()}"])->all();
    }
}
