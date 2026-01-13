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
        Schema::table('product_order_batches', function (Blueprint $table) {
            $table->dropUnique('product_order_batches_batch_number_unique');
            $table->string('batch_number')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_order_batches', function (Blueprint $table) {
            $table->unique('batch_number');
        });
    }
};
