<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Grammar;

final class ContractOnlyExpression implements ExpressionContract
{
    public function __construct(private readonly string $value)
    {
    }

    public function getValue(Grammar $grammar): string
    {
        return $this->value;
    }
}
