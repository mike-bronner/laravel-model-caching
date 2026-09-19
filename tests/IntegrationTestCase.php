<?php namespace GeneaLabs\LaravelModelCaching\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class IntegrationTestCase extends BaseTestCase
{
    use CreatesApplication;
    // Testbench calls setUpFaker() from setUpTraits() when this trait is in the
    // uses map, so $this->faker is ready after any parent::setUp().
    use WithFaker;
}
