<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Cachable pivot model for the role_user table.
 * Used to test that writes to a pivot table invalidate relations joining it.
 */
class CachableRoleUser extends Pivot
{
    use Cachable;

    protected $table = 'role_user';
    protected $fillable = [
        'role_id',
        'user_id',
    ];
}
