<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role groups permissions. System roles (seeded from the brief) can be
 * re-permissioned but not deleted or renamed.
 */
class Role extends SpatieRole
{
    use Auditable;

    protected $fillable = ['name', 'guard_name', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }
}
