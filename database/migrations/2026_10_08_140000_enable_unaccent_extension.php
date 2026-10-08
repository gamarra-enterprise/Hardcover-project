<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Lets the catalog search ignore accents: "cronicas" finds "Crónicas marcianas". */
    public function up(): void
    {
        DB::statement('create extension if not exists unaccent');
    }

    public function down(): void
    {
        DB::statement('drop extension if exists unaccent');
    }
};
