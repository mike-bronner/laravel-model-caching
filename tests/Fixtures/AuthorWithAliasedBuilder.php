<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthorWithAliasedBuilder extends Model
{
    use Cachable {
        newEloquentBuilder as cachableNewEloquentBuilder;
    }
    use SoftDeletes;

    protected $table = "authors";

    public function newEloquentBuilder($query)
    {
        return $this->cachableNewEloquentBuilder($query);
    }
}
