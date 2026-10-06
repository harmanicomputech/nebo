<?php

namespace App\Policies;

use App\Models\ConditionReport;
use App\Models\User;

/** Condition reports (and their photos) follow the asset's visibility. */
class ConditionReportPolicy
{
    public function view(User $user, ConditionReport $report): bool
    {
        return $user->can('inventory.view') || $user->can('maintenance.view') || $user->can('maintenance.view_assigned');
    }

    /** Append-only: photos are added at inspection time, never removed. */
    public function update(User $user, ConditionReport $report): bool
    {
        return false;
    }
}
