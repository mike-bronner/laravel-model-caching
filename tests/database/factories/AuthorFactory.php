<?php namespace GeneaLabs\LaravelModelCaching\Tests\Database\Factories;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorFactory extends Factory
{
    protected $model = Author::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'finances' => [
                'total' => 5000,
                'weekly' => 100,
                'daily' => 20,
                'tags' => ['foo', 'bar'],
            ],
            'is_famous' => $this->faker->boolean(),
        ];
    }
}