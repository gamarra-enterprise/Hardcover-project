<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_details', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained('books')->cascadeOnDelete();
            $table->string('isbn_10', 10)->nullable()->unique();
            $table->string('isbn_13', 13)->nullable()->unique();
            $table->string('author');
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->unsignedSmallInteger('pages')->nullable();
            $table->string('format', 30)->nullable();

            $table->index('author');
        });

        DB::statement('insert into book_details (product_id, isbn_10, isbn_13, author, publisher, published_year)
            select id, isbn_10, isbn_13, author, publisher, published_year from books');

        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['author']);
            $table->dropUnique(['isbn_10']);
            $table->dropUnique(['isbn_13']);
            $table->dropColumn(['isbn_10', 'isbn_13', 'author', 'publisher', 'published_year']);
            $table->renameColumn('title', 'name');
            $table->renameColumn('cover_path', 'image_path');
        });

        Schema::rename('books', 'products');
        Schema::rename('book_category', 'category_product');

        Schema::table('category_product', fn (Blueprint $table) => $table->renameColumn('book_id', 'product_id'));
        Schema::table('cart_items', fn (Blueprint $table) => $table->renameColumn('book_id', 'product_id'));
        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('book_id', 'product_id');
            $table->renameColumn('book_snapshot', 'product_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('product_id', 'book_id');
            $table->renameColumn('product_snapshot', 'book_snapshot');
        });
        Schema::table('cart_items', fn (Blueprint $table) => $table->renameColumn('product_id', 'book_id'));
        Schema::table('category_product', fn (Blueprint $table) => $table->renameColumn('product_id', 'book_id'));

        Schema::rename('category_product', 'book_category');
        Schema::rename('products', 'books');

        Schema::table('books', function (Blueprint $table) {
            $table->renameColumn('name', 'title');
            $table->renameColumn('image_path', 'cover_path');
            $table->string('isbn_10', 10)->nullable()->unique();
            $table->string('isbn_13', 13)->nullable()->unique();
            $table->string('author')->default('');
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->index('author');
        });

        // Products that are not books have no row in book_details and keep an empty author.
        DB::statement('update books set isbn_10 = d.isbn_10, isbn_13 = d.isbn_13, author = d.author,
            publisher = d.publisher, published_year = d.published_year
            from book_details d where d.product_id = books.id');

        Schema::dropIfExists('book_details');
    }
};
