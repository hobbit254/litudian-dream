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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('category_id')->unsigned();
            $table->string('uuid')->unique();
            $table->string('product_name')->unique();
            $table->float('price');
            $table->float('original_price');
            $table->integer('minimum_order_quantity');
            $table->float('estimated_shipping_cost');
            $table->longText('description');
            $table->boolean('campaign_product');
            $table->boolean('recent_product');
            $table->string('image');
            $table->jsonb('specifications')->nullable();
            $table->boolean('in_stock')->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('categories')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }

};
