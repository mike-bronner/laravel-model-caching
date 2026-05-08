<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\ContractOnlyExpression;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class ContractExpressionTest extends IntegrationTestCase
{
    /**
     * Regression for PR #590. A custom Expression that implements
     * Illuminate\Contracts\Database\Query\Expression but does NOT extend
     * the concrete Illuminate\Database\Query\Expression must still be
     * recognized by CacheKey's instanceof checks. Otherwise the object
     * falls through into string concatenation in getOtherClauses() and
     * triggers a fatal "Object of class … could not be converted to string".
     */
    public function test_cache_key_handles_contract_only_expression_as_where_column()
    {
        $expression = new ContractOnlyExpression('id');

        $cached = (new Book)
            ->where($expression, '>', 0)
            ->get();

        $live = (new UncachedBook)
            ->where($expression, '>', 0)
            ->get();

        $this->assertNotEmpty($cached);
        $this->assertEmpty($cached->diffKeys($live));
    }
}
