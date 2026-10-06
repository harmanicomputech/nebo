<?php

namespace App\Notifications;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Support\Format;

class QuotationResponded extends NeboNotification
{
    public function __construct(public Quotation $quotation) {}

    public function title(): string
    {
        return $this->quotation->status === QuotationStatus::Accepted ? 'Quotation accepted' : 'Quotation declined';
    }

    public function body(): string
    {
        return "{$this->quotation->reference} ({$this->quotation->title}, ".Format::naira($this->quotation->total_kobo).") was {$this->quotation->status->value} by {$this->quotation->responded_by_name}.";
    }

    public function url(): ?string
    {
        return route('app.quotations.show', $this->quotation);
    }

    public function level(): string
    {
        return $this->quotation->status === QuotationStatus::Accepted ? 'success' : 'warning';
    }

    public function icon(): string
    {
        return 'receipt';
    }
}
