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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->integer('receive_email_alerts')->default(0);
            $table->integer('receive_sms_alerts')->default(0);
            $table->float('deposit_fee_percent')->default(0);
            $table->integer('deposit_days_due')->default(0);
            $table->integer('balance_days_due')->default(0);
            $table->integer('shipping_days_due')->default(0);
            $table->integer('anonymous_buying')->default(0);
            $table->float('service_fee_percent')->default(0);
            $table->float('service_fee_cap')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
