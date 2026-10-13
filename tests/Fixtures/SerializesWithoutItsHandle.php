<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

final class SerializesWithoutItsHandle
{
    public readonly mixed $handle;

    public function __construct(public readonly string $name)
    {
        $this->handle = fopen("php://memory", "r");
    }

    public function __serialize(): array
    {
        return ["name" => $this->name];
    }
}
