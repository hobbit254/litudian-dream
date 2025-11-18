<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_schedule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            // --- Deposit Fields ---
            $table->decimal('deposit_amount', 10, 2);
            $table->boolean('deposit_paid')->default(false);
            $table->date('deposit_due_date');
            $table->string('deposit_receipt')->nullable();

            // --- Balance Fields ---
            $table->decimal('balance_amount', 10, 2);
            $table->boolean('balance_paid')->default(false);
            $table->date('balance_due_date')->nullable();
            $table->string('balance_receipt')->nullable();

            // --- Shipping Fields ---
            $table->decimal('shipping_amount', 10, 2);
            $table->boolean('shipping_paid')->default(false);
            $table->date('shipping_due_date')->nullable();
            $table->string('shipping_receipt')->nullable();

            // --- Service Fee ---
            $table->decimal('service_fee', 10, 2);
            $table->foreign('order_id')->references('id')->on('orders')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_schedule');
    }
};
