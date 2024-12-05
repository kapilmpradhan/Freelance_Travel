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
        Schema::table('products', function (Blueprint $table) {
            $table->string('tdms_product_last_update_date')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('product_id', 'tdms_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('tdms_product_id', 'product_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tdms_product_last_update_date');
        });
    }
};
