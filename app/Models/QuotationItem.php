<?php

namespace App\Models;

use App\Enums\QuoteSection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['quotation_id', 'section', 'description', 'service_id', 'equipment_id', 'quantity', 'days', 'unit_price_kobo', 'line_total_kobo', 'sort_order'])]
class QuotationItem extends Model
{
    protected function casts(): array
    {
        return ['section' => QuoteSection::class, 'quantity' => 'integer', 'days' => 'integer', 'unit_price_kobo' => 'integer', 'line_total_kobo' => 'integer'];
    }
}
