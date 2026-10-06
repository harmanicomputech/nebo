<?php

namespace App\Enums;

/**
 * Event production request workflow (brief §16). Transitions are explicit;
 * every change is recorded in status_changes by RequestWorkflow.
 */
enum RequestStatus: string
{
    case New = 'new';
    case UnderReview = 'under_review';
    case Contacted = 'contacted';
    case SiteAssessment = 'site_assessment_required';
    case ProductionPlanning = 'production_planning';
    case QuotationPreparation = 'quotation_preparation';
    case QuotationSent = 'quotation_sent';
    case AwaitingCustomer = 'awaiting_customer';
    case Confirmed = 'confirmed';
    case DepositPending = 'deposit_pending';
    case Approved = 'approved';
    case ProductionScheduled = 'production_scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::SiteAssessment => 'Site Assessment Required',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        $exit = [self::Cancelled, self::Declined];

        return match ($this) {
            self::New => [self::UnderReview, self::Contacted, ...$exit],
            self::UnderReview => [self::Contacted, self::SiteAssessment, self::ProductionPlanning, self::QuotationPreparation, ...$exit],
            self::Contacted => [self::SiteAssessment, self::ProductionPlanning, self::QuotationPreparation, self::AwaitingCustomer, ...$exit],
            self::SiteAssessment => [self::ProductionPlanning, self::QuotationPreparation, self::AwaitingCustomer, ...$exit],
            self::ProductionPlanning => [self::QuotationPreparation, self::AwaitingCustomer, ...$exit],
            self::QuotationPreparation => [self::QuotationSent, self::ProductionPlanning, ...$exit],
            self::QuotationSent => [self::AwaitingCustomer, self::Confirmed, self::QuotationPreparation, ...$exit],
            self::AwaitingCustomer => [self::Confirmed, self::QuotationPreparation, self::Contacted, ...$exit],
            self::Confirmed => [self::DepositPending, self::Approved, self::Cancelled],
            self::DepositPending => [self::Approved, self::Cancelled],
            self::Approved => [self::ProductionScheduled, self::Cancelled],
            self::ProductionScheduled => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled, self::Declined => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled, self::Declined], true);
    }

    /** Requests that turned into business. */
    public function isWon(): bool
    {
        return in_array($this, [self::Confirmed, self::DepositPending, self::Approved, self::ProductionScheduled, self::Completed], true);
    }

    public function tone(): string
    {
        return match (true) {
            $this === self::New => 'brand',
            $this === self::Completed => 'success',
            $this->isWon() => 'success',
            in_array($this, [self::Cancelled, self::Declined], true) => 'neutral',
            in_array($this, [self::QuotationSent, self::AwaitingCustomer], true) => 'info',
            default => 'warning',
        };
    }

    /** The simple stage a customer sees on the tracking page (no internal terms). */
    public function publicStage(): int
    {
        return match ($this) {
            self::New => 1,
            self::UnderReview, self::Contacted, self::SiteAssessment, self::ProductionPlanning => 2,
            self::QuotationPreparation, self::QuotationSent, self::AwaitingCustomer => 3,
            self::Confirmed, self::DepositPending, self::Approved, self::ProductionScheduled => 4,
            self::Completed => 5,
            self::Cancelled, self::Declined => 0,
        };
    }

    /**
     * @return list<string>
     */
    public static function publicStages(): array
    {
        return [1 => 'Request received', 2 => 'Being reviewed', 3 => 'Quotation', 4 => 'Confirmed', 5 => 'Delivered'];
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isOpen())));
    }
}
