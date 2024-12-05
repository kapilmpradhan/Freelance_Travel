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
        Schema::table('cart_items', function (Blueprint $table) {
            $table->renameColumn('product_id', 'tdms_product_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->renameColumn('product_price_id', 'product_price_details_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->renameColumn('tdms_product_id', 'product_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->renameColumn('product_price_details_id', 'product_price_id');
        });
    }
};
