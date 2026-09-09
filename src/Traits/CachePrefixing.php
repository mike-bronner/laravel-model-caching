<?php namespace GeneaLabs\LaravelModelCaching\Traits;

use Illuminate\Container\Container;

trait CachePrefixing
{
    protected function getCachePrefix() : string
    {
        return $this->getCachePrefixForModel($this->model);
    }

    /**
     * The cache prefix a given model reads and writes its own tags under.
     *
     * A tag only invalidates a cached query when the reader and the writer
     * spell it identically, and two of the prefix segments are per-model: the
     * connection and database come from that model's own connection, and the
     * `$cachePrefix` property is declared on the model itself. Tagging a
     * related table with the *querying* model's prefix therefore produces a tag
     * the related model's own write never flushes, and the cached query stays
     * stale for as long as the entry lives.
     *
     * So every tag naming a table is built from the model that owns that table
     * where the caller can identify it. Where it cannot — a raw whereExists()
     * closure, or a join naming a bare table — there is no model to ask, and
     * the querying model's prefix stays in place as before.
     *
     * @param  \Illuminate\Database\Eloquent\Model|object|null  $model
     */
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

    /**
     * The database and connection names a given model keys its cache under.
     *
     * These read the model's own connection, except for the model this object
     * was built for: that one goes back through getDatabaseName() and
     * getConnectionName(), which are the published extension points and may be
     * overridden downstream. Routing the querying model through them keeps such
     * an override in force, which it would not be if these read the connection
     * directly for every model.
     *
     * The two named methods hold the real lookup and never call back here, so
     * the delegation runs one way only.
     *
     * The identity test is on the object, not the class. A related model is a
     * different instance even when it is the same class, so it takes the direct
     * path and keeps its own prefix — which is what makes a related table's tag
     * match the tag that model's own write flushes.
     *
     * @param  \Illuminate\Database\Eloquent\Model|object  $model
     */
    protected function getDatabaseNameForModel($model) : string
    {
        if ($model === $this->model) {
            return $this->getDatabaseName();
        }

        return $model->getConnection()->getDatabaseName();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model|object  $model
     *
     * @see self::getDatabaseNameForModel()
     */
    protected function getConnectionNameForModel($model) : string
    {
        if ($model === $this->model) {
            return $this->getConnectionName();
        }

        return $model->getConnection()->getName();
    }
}
