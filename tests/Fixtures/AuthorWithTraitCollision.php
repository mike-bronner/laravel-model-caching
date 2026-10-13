<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthorWithTraitCollision extends Model
{
    use Cachable, FakeNodeTrait {
        Cachable::newEloquentBuilder insteadof FakeNodeTrait;
    }
    use SoftDeletes;

    protected $table = 'authors';
    protected $fillable = [
        'name',
        'email',
        'is_famous',
    ];
}
