<?php

namespace Database\Factories;

use App\Models\BookDetail;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookDetail>
 */
class BookDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'isbn_13' => fake()->unique()->isbn13(),
            'author' => fake()->name(),
            'publisher' => fake()->company(),
            'published_year' => fake()->numberBetween(1900, 2025),
            'pages' => fake()->numberBetween(80, 800),
            'format' => fake()->randomElement(['Tapa blanda', 'Tapa dura']),
            'genres' => 'ficción / novela',
            'author_bio' => fake()->paragraph(),
        ];
    }
}
