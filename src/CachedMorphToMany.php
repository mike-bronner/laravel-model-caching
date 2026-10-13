<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use GeneaLabs\LaravelModelCaching\Traits\Buildable;
use GeneaLabs\LaravelModelCaching\Traits\BuilderCaching;
use GeneaLabs\LaravelModelCaching\Traits\CachedPivotOperations;
use GeneaLabs\LaravelModelCaching\Traits\Caching;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class CachedMorphToMany extends MorphToMany
{
    use Buildable;
    use BuilderCaching;
    use Caching;
    use CachedPivotOperations;
}
