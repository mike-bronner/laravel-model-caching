<?php namespace GeneaLabs\LaravelModelCaching\Tests\Database\Factories;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublisherFactory extends Factory
{
    protected $model = Publisher::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}