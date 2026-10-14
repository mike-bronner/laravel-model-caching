<?php

namespace GeneaLabs\LaravelModelCaching\Tests\TraitCollisionForms;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Concerns\DefinesBaseQueryBuilder;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

class AuthorPreferringOtherBaseBuilder extends Model
{
    use Cachable;
    use DefinesBaseQueryBuilder {
        DefinesBaseQueryBuilder::newBaseQueryBuilder insteadof Cachable;
    }

    protected $table = "authors";
}
