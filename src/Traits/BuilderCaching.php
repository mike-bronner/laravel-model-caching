<?php

namespace GeneaLabs\LaravelModelCaching\Traits;

use Illuminate\Database\Eloquent\Collection;

trait BuilderCaching
{
    public function all($columns = ['*']) : Collection
    {
        $model = $this->cacheModel();

        if (! $this->isCachable()) {
            $model->disableModelCaching();
        }

        return $model->get($columns);
    }

    public function truncate()
    {
        $this->flushCacheAfterBuilderWrite('truncate');

        return parent::truncate();
    }

    public function withoutGlobalScope($scope)
    {
        array_push($this->withoutGlobalScopes, $scope);

        return parent::withoutGlobalScope($scope);
    }

    public function withoutGlobalScopes(?array $scopes = null)
    {
        if ($scopes !== null) {
            $this->withoutGlobalScopes = $scopes;
        }

        if (
            $scopes == null
            || (
                $scopes !== null
                && count($scopes) === 0
            )
        ) {
            $this->withoutAllGlobalScopes = true;
        }

        return parent::withoutGlobalScopes($scopes);
    }
}
