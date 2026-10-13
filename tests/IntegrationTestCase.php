<?php namespace GeneaLabs\LaravelModelCaching\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class IntegrationTestCase extends BaseTestCase
{
    use CreatesApplication;
    use WithFaker;
}
