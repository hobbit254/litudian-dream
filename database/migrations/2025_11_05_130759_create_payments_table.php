<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('payment_method');
            $table->string('phone_number');
            $table->decimal('payment_amount', 10, 2);
            $table->string('payment_status');
            $table->json('payment_history');
            $table->string('merchant_ref');
            $table->string('payment_unique_ref')->unique();
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
        Schema::dropIfExists('payments');
    }
};
