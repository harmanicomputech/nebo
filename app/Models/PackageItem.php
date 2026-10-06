<?php

namespace App\Models;

use App\Enums\QuoteSection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['package_id', 'section', 'description', 'service_id', 'equipment_id', 'quantity', 'days', 'unit_price_kobo', 'sort_order'])]
class PackageItem extends Model
{
    protected function casts(): array
    {
        return ['section' => QuoteSection::class, 'quantity' => 'integer', 'days' => 'integer', 'unit_price_kobo' => 'integer'];
    }
}
