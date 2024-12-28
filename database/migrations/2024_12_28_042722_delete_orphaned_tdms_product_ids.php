<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deletes rows in cart_items and product_price_availabilities
     * that does not have respective product so that constraints can be added
     * in a migration to follow
     */
    public function up(): void
    {
        // Delete orphaned tdms_product_id from product_price_availabilities
        DB::table('product_price_availabilities')
            ->whereNotIn('tdms_product_id', function ($query) {
                $query->select('tdms_product_id')->from('products');
            })
            ->delete();

        // Delete orphaned tdms_product_id from cart_items
        DB::table('cart_items')
            ->whereNotIn('tdms_product_id', function ($query) {
                $query->select('tdms_product_id')->from('products');
            })
            ->delete();
        // Delete orphaned product_price_details_id from cart_items
        DB::table('cart_items')
            ->whereNotIn('product_price_details_id', function ($query) {
                $query->select('product_price_details_id')->from('product_price_availabilities');
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // no op
    }
};
