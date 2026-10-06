<?php

namespace App\Services\Inventory;

use App\Models\EquipmentAsset;
use App\Services\ReferenceGenerator;

/**
 * Asset tags like ML-001. The counter is per prefix and shared by every
 * equipment item using that prefix; manually entered tags are skipped.
 */
class AssetTagGenerator
{
    public function __construct(private ReferenceGenerator $references) {}

    public function next(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix, '- '));

        do {
            $number = $this->references->increment('asset:'.$prefix);
            $tag = $prefix.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        } while (EquipmentAsset::withTrashed()->where('asset_tag', $tag)->exists());

        return $tag;
    }
}
