<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

final class SleepsWithChosenProperties
{
    protected mixed $handle;

    public function __construct(protected readonly array $sleepingProperties)
    {
        $this->handle = fopen("php://memory", "r");
    }

    public function __sleep(): array
    {
        return $this->sleepingProperties;
    }
}
