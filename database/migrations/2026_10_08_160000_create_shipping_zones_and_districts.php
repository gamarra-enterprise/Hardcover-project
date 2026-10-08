<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A zone groups districts and sets the lowest price any of them may charge.
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->decimal('min_cost', 10, 2);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_districts', function (Blueprint $table) {
            $table->id();
            // A zone with districts cannot be deleted by accident.
            $table->foreignId('shipping_zone_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->char('ubigeo', 6)->unique();
            $table->decimal('cost', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['shipping_zone_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_districts');
        Schema::dropIfExists('shipping_zones');
    }
};
