<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Stringable;

final class KeepsItsNamePrivate implements Stringable
{
    public function __construct(private readonly string $name)
    {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
