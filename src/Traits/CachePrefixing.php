<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use Illuminate\Container\Container;

trait CachePrefixing
{
    protected function getCachePrefix() : string
    {
        return $this->getCachePrefixForModel($this->model);
    }

    protected function getCachePrefixForModel($model) : string
    {
        $config = Container::getInstance()
            ->make("config");
        $cachePrefix = "genealabs:laravel-model-caching:";

        if ($config->get("laravel-model-caching.use-database-keying")) {
            $cachePrefix .= $this->getConnectionNameForModel($model) . ":";
            $cachePrefix .= $this->getDatabaseNameForModel($model) . ":";
        }

        $cachePrefix .= $config->get("laravel-model-caching.cache-prefix", "");

        if ($model
            && property_exists($model, "cachePrefix")
        ) {
            $cachePrefix .= $model->cachePrefix . ":";
        }

        return $cachePrefix;
    }

    protected function getDatabaseName() : string
    {
        return $this->model->getConnection()->getDatabaseName();
    }

    protected function getConnectionName() : string
    {
        return $this->model->getConnection()->getName();
    }

    protected function getDatabaseNameForModel($model) : string
    {
        if ($model === $this->model) {
            return $this->getDatabaseName();
        }

        return $model->getConnection()->getDatabaseName();
    }

    protected function getConnectionNameForModel($model) : string
    {
        if ($model === $this->model) {
            return $this->getConnectionName();
        }

        return $model->getConnection()->getName();
    }
}
