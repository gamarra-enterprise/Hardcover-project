<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\BookDetail;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'sku' => strtoupper(fake()->unique()->bothify('HB-####-??')),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 150),
            'sale_price' => null,
            'stock' => fake()->numberBetween(0, 40),
            'weight_grams' => fake()->numberBetween(30, 700),
            'width_mm' => fake()->numberBetween(30, 150),
            'height_mm' => fake()->numberBetween(45, 230),
            'depth_mm' => fake()->numberBetween(4, 40),
            'status' => ProductStatus::ACTIVE,
        ];
    }

    /** A product with its book_details row. */
    public function book(): static
    {
        return $this->afterCreating(function (Product $product) {
            BookDetail::factory()->for($product)->create();
        });
    }

    public function onSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_price' => round($attributes['price'] * 0.8, 2),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }

    public function hidden(): static
    {
        return $this->state(['status' => ProductStatus::HIDDEN]);
    }
}
