<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthorWithCachedCustomBuilder extends Model
{
    use Cachable;
    use SoftDeletes;

    protected $table = 'authors';

    protected static string $builder = AuthorCachedQueryBuilder::class;

    protected $fillable = [
        'name',
        'email',
        'is_famous',
    ];
}
