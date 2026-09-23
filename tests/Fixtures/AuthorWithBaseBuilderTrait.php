<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Concerns\DefinesBaseQueryBuilder;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

/**
 * A cachable Author that also mixes in another trait overriding
 * newBaseQueryBuilder(). Merely loading this class proves the two traits do
 * not collide.
 */
class AuthorWithBaseBuilderTrait extends Model
{
    use Cachable;
    use DefinesBaseQueryBuilder;

    protected $table = 'authors';

    protected $fillable = [
        'name',
        'email',
        'is_famous',
    ];
}
