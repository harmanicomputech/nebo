<?php

namespace App\Policies;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;

/**
 * quotations.manage prepares drafts and records answers; sending a
 * quotation to the customer needs quotations.approve (D60).
 */
class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotations.view');
    }

    public function view(User $user, Quotation $quote): bool
    {
        return $user->can('quotations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quotations.manage');
    }

    public function update(User $user, Quotation $quote): bool
    {
        return $user->can('quotations.manage') && $quote->status === QuotationStatus::Draft && ! $quote->trashed();
    }

    public function send(User $user, Quotation $quote): bool
    {
        return $user->can('quotations.approve') && $quote->status === QuotationStatus::Draft;
    }

    public function respond(User $user, Quotation $quote): bool
    {
        return $user->can('quotations.manage') && $quote->status === QuotationStatus::Sent;
    }

    public function revise(User $user, Quotation $quote): bool
    {
        return $user->can('quotations.manage') && $quote->status->canMoveTo(QuotationStatus::Draft) && $quote->status !== QuotationStatus::Draft;
    }

    public function cancel(User $user, Quotation $quote): bool
    {
        return $user->can('quotations.manage') && $quote->status->canMoveTo(QuotationStatus::Cancelled);
    }

    public function addNote(User $user, Quotation $quote): bool
    {
        return $user->can('quotations.manage');
    }
}
