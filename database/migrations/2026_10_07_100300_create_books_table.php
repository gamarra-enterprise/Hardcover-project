<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('isbn_10', 10)->nullable()->unique();
            $table->string('isbn_13', 13)->nullable()->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('author');
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();

            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);

            // Physical metrics for shipping calculation
            $table->unsignedInteger('weight_grams');
            $table->unsignedSmallInteger('width_mm');
            $table->unsignedSmallInteger('height_mm');
            $table->unsignedSmallInteger('depth_mm');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('title');
            $table->index('author');
            $table->index(['is_active', 'price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
