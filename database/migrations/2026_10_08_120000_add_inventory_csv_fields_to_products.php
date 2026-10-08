<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The inventory sheet has no weight and no depth, so measures are optional.
        // Shipping must fall back to a default when they are missing.
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weight_grams')->nullable()->change();
            $table->unsignedSmallInteger('width_mm')->nullable()->change();
            $table->unsignedSmallInteger('height_mm')->nullable()->change();
            $table->unsignedSmallInteger('depth_mm')->nullable()->change();

            // PDC column of the sheet: what the store paid. Staff only, never shown in the shop.
            $table->decimal('cost_price', 10, 2)->nullable()->after('sale_price');
        });

        Schema::table('book_details', function (Blueprint $table) {
            // GÉNERO column, kept whole as descriptive and searchable text. Only the main
            // genres become categories (the shop filters).
            $table->text('genres')->nullable()->after('format');
            // INFO AUTOR column.
            $table->text('author_bio')->nullable()->after('genres');
        });
    }

    public function down(): void
    {
        Schema::table('book_details', fn (Blueprint $table) => $table->dropColumn(['genres', 'author_bio']));

        DB::table('products')->whereNull('weight_grams')->update(['weight_grams' => 0]);
        DB::table('products')->whereNull('width_mm')->update(['width_mm' => 0]);
        DB::table('products')->whereNull('height_mm')->update(['height_mm' => 0]);
        DB::table('products')->whereNull('depth_mm')->update(['depth_mm' => 0]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost_price');
            $table->unsignedInteger('weight_grams')->nullable(false)->change();
            $table->unsignedSmallInteger('width_mm')->nullable(false)->change();
            $table->unsignedSmallInteger('height_mm')->nullable(false)->change();
            $table->unsignedSmallInteger('depth_mm')->nullable(false)->change();
        });
    }
};
