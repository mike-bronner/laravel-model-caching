<?php namespace GeneaLabs\LaravelModelCaching\Tests\Database\Factories;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'author_id' => 1,
            'title' => $this->faker->title(),
            'description' => $this->faker->optional()->paragraphs(3, true),
            'published_at' => $this->faker->dateTime(),
            'price' => $this->faker->randomFloat(2, 0, 999999),
            'publisher_id' => 1,
        ];
    }
}