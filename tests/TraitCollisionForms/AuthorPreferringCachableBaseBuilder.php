<?php

namespace GeneaLabs\LaravelModelCaching\Tests\TraitCollisionForms;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Concerns\DefinesBaseQueryBuilder;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

class AuthorPreferringCachableBaseBuilder extends Model
{
    use Cachable;
    use DefinesBaseQueryBuilder {
        Cachable::newBaseQueryBuilder insteadof DefinesBaseQueryBuilder;
    }

    protected $table = "authors";
}
