<?php namespace GeneaLabs\LaravelModelCaching\Tests\Database\Factories;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
        ];
    }
}