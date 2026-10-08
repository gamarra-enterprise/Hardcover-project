<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Contact for guest orders and for the status e-mails.
            $table->string('email')->nullable()->after('tracking_code');
            // Set when the payment completes and the stock goes down; cleared if it is given back.
            $table->timestamp('stock_deducted_at')->nullable()->after('total');
            // What is owed to the customer after a cancellation, until the refund is made.
            $table->decimal('refund_amount', 10, 2)->nullable()->after('stock_deducted_at');
        });

        // One row per change of status: who, when and why. Rows are never edited.
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['email', 'stock_deducted_at', 'refund_amount']);
        });
    }
};
