<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add foreign key reference to the tdms_product_id of products table
     * and make combination of tdms_product_id and product_price_details_id unique
     */
    public function up(): void
    {
        Schema::table('product_price_availabilities', function (Blueprint $table) {
            // Add foreign key constraint to tdms_product_id
            $table->foreign('tdms_product_id')
                  ->references('tdms_product_id')
                  ->on('products')
                  ->onDelete('cascade');

            // Add unique constraint on (tdms_product_id, product_price_details_id)
            $table->unique(['tdms_product_id', 'product_price_details_id'], 'unique_tdms_product_id_price_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_price_availabilities', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique('unique_tdms_product_id_price_details');

            // Drop the foreign key constraint
            $table->dropForeign(['tdms_product_id']);
        });
    }
};
