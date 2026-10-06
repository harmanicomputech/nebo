<?php

namespace App\Models;

use App\Enums\AssetStatusGroup;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * An equipment status (Available, Deployed, Under Maintenance, …).
 * Code reads `group`, `is_allocatable` and `is_manual`; never the label.
 */
#[Fillable(['code', 'label', 'description', 'group', 'tone', 'is_allocatable', 'is_manual', 'is_system', 'is_active', 'sort_order'])]
class AssetStatus extends Model
{
    use Auditable;

    public const TONES = ['neutral', 'success', 'warning', 'danger', 'info', 'brand', 'dark'];

    protected function casts(): array
    {
        return [
            'group' => AssetStatusGroup::class,
            'is_allocatable' => 'boolean',
            'is_manual' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<EquipmentAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(EquipmentAsset::class, 'status_id');
    }

    public static function byCode(string $code): self
    {
        return self::where('code', $code)->firstOrFail();
    }

    /**
     * @return Collection<int, AssetStatus>
     */
    public static function ordered(): Collection
    {
        return self::query()->orderBy('sort_order')->orderBy('label')->get();
    }
}
