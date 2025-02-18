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
        Schema::table('cart_customer_details', function (Blueprint $table) {
            $table->dropUnique('cart_customer_details_user_id_customer_index_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_customer_details', function (Blueprint $table) {
            $table->unique(['user_id', 'customer_index'], 'cart_customer_details_user_id_customer_index_unique');
        });
    }
};
