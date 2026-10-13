<?php namespace GeneaLabs\LaravelModelCaching\Tests\Database\Factories;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Database\Eloquent\Factories\Factory;

class UncachedAuthorFactory extends Factory
{
    protected $model = UncachedAuthor::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'finances' => [
                'total' => 5000,
                'weekly' => 100,
                'daily' => 20,
            ],
        ];
    }
}