<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'logistics')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        // The removed 'logistics' role cannot be told apart from 'admin' anymore.
    }
};
