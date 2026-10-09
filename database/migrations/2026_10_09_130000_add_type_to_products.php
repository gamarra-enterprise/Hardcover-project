<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->string('type', 20)->default('libro')->index()->after('slug'));

        // Whatever has no book details was already stationery, an accessory or a figure: tell them apart by category.
        DB::statement(<<<'SQL'
            update products set type = case
                when exists (select 1 from category_product cp join categories c on c.id = cp.category_id where cp.product_id = products.id and c.name = 'Figuras') then 'figura'
                when exists (select 1 from category_product cp join categories c on c.id = cp.category_id where cp.product_id = products.id and c.name = 'Accesorios') then 'accesorio'
                else 'papeleria' end
            where not exists (select 1 from book_details b where b.product_id = products.id)
        SQL);
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('type'));
    }
};
