<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

class PrinterWithTtl extends Printer
{
    protected $table = "printers";
    protected $cacheTtlSeconds = 30;
}
