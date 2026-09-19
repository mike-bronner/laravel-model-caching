<?php namespace GeneaLabs\LaravelModelCaching\Tests\Database\Factories;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'supplier_id' => SupplierFactory::new()->create()->id,
        ];
    }
}