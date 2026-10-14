<?php

function throwsOnFirstCall(Closure $constraint): Closure
{
    $calls = 0;

    return function ($query) use (&$calls, $constraint) {
        throw_if($calls++ === 0, RuntimeException::class, "first call failed");

        $constraint($query);
    };
}

function streamHolding(string $contents)
{
    $stream = fopen("php://memory", "r+");
    fwrite($stream, $contents);
    rewind($stream);

    return $stream;
}

function selfReferencingValue(mixed $neighbour): object
{
    $value = (object) ["items" => [null, $neighbour]];
    $value->items[0] = &$value->items;

    return $value;
}
