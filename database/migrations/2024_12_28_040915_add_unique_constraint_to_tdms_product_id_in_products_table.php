<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Makes the tdms_product_id of products table unique so that 
     * All duplicate tdms_product_id should have been cleaned
     * by the migration 2024_12_27_102548_filter_products_table
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unique('tdms_product_id', 'unique_tdms_product_id'); // Add unique constraint
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('unique_tdms_product_id'); // Drop the unique constraint
        });
    }
};
