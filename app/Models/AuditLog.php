<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One audited action. Append-only: rows cannot be changed or removed
 * through the application.
 */
#[Fillable(['user_id', 'user_name', 'event', 'auditable_type', 'auditable_id', 'description', 'old_values', 'new_values', 'ip_address', 'user_agent', 'url', 'created_at'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit log entries cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Attribute names that changed, for the viewer.
     *
     * @return list<string>
     */
    public function changedKeys(): array
    {
        return array_values(array_unique(array_merge(array_keys($this->old_values ?? []), array_keys($this->new_values ?? []))));
    }
}
