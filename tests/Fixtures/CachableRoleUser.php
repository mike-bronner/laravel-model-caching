<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

class CachableRoleUser extends Model
{
    use Cachable;

    protected $table = 'role_user';
    protected $fillable = [
        'role_id',
        'user_id',
    ];
}
