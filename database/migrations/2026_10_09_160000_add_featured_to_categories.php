<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Featured categories are the ones shown as filters and in the menu; the rest sit under "Más géneros".
        Schema::table('categories', fn (Blueprint $table) => $table->boolean('featured')->default(false)->after('is_active'));

        DB::statement('update categories set featured = true where id in (select category_id from category_product group by category_id order by count(*) desc limit 8)');
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('featured'));
    }
};
