<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Database\Factories;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPublisher;
use Illuminate\Database\Eloquent\Factories\Factory;

class UncachedPublisherFactory extends Factory
{
    protected $model = UncachedPublisher::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}