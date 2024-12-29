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
            $table->dropForeign('fk_composite_cart_items'); // Drop the foreign key
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreign(['tdms_product_id', 'product_price_details_id'])
                  ->references(['tdms_product_id', 'product_price_details_id'])
                  ->on('product_price_availabilities')
                  ->onDelete('cascade'); // Re-add the foreign key in the down method
        });
    }
};
