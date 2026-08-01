<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cachable model over the role_user pivot table.
 * Used to test that writing pivot rows through their own cachable model
 * invalidates relation caches that join that pivot table (issue #610).
 */
class CachableRoleUser extends Model
{
    use Cachable;

    protected $table = 'role_user';

    protected $fillable = [
        'role_id',
        'user_id',
    ];
}
