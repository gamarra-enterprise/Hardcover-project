<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30); // stripe | mercadopago
            $table->string('external_reference')->nullable();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('PEN');
            $table->string('status', 30)->default(PaymentStatus::PENDING->value)->index();

            // Raw gateway response, kept for audit.
            $table->jsonb('payload')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->unique(['provider', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
