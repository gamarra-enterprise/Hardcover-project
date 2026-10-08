<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('stock');
        });

        DB::table('products')->where('stock', 0)->update(['status' => 'out_of_stock']);
        DB::table('products')->where('is_active', false)->update(['status' => 'hidden']);

        // The index kept its original "books_" name when the table was renamed, unless a
        // rollback recreated it with the "products_" name, so both names are dropped.
        DB::statement('drop index if exists books_is_active_price_index');
        DB::statement('drop index if exists products_is_active_price_index');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_active');
            $table->index(['status', 'price']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('depth_mm');
        });

        DB::table('products')->where('status', 'hidden')->update(['is_active' => false]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status', 'price']);
            $table->dropColumn('status');
            $table->index(['is_active', 'price']);
        });
    }
};
