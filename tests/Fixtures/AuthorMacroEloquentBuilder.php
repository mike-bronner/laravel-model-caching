<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Query\Builder as QueryBuilder;

class AuthorMacroEloquentBuilder extends AuthorSubqueryEloquentBuilder
{
    public function __construct(QueryBuilder $query)
    {
        parent::__construct($query);

        $this->macro("whereIn", $this->whereInExcludingTheFirstAuthor());
    }
}
