<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use GeneaLabs\LaravelPivotEvents\Traits\PivotEventTrait;

trait Cachable
{
    use Caching;
    use ModelCaching;
    use PivotEventTrait {
        ModelCaching::newBelongsToMany insteadof PivotEventTrait;
        ModelCaching::newMorphToMany insteadof PivotEventTrait;
    }

    public function __wakeup(): void
    {
        $original = $this->original;
        $attributes = $this->attributes;
        $relations = $this->relations;
        $exists = $this->exists;
        $connection = $this->connection ?? null;

        $this->__construct();

        $this->attributes = $attributes;
        $this->original = $original;
        $this->relations = $relations;
        $this->exists = $exists;
        $this->connection = $connection;

        parent::__wakeup();
    }
}
