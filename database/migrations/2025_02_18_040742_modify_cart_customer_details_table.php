<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cart_customer_details', function (Blueprint $table) {
            // Drop foreign key constraint before removing unique constraint
            $table->dropForeign('cart_customer_details_user_id_foreign');

            // Drop the unique constraint
            $table->dropUnique('cart_customer_details_user_id_customer_index_unique');

            // (Optional) Re-add the foreign key if needed
            $table->foreign('user_id')->references('uuid')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('cart_customer_details', function (Blueprint $table) {
            // Re-add the unique constraint
            $table->unique(['user_id', 'customer_index'], 'cart_customer_details_user_id_customer_index_unique');

            // Re-add the foreign key constraint
            $table->foreign('user_id')->references('uuid')->on('users')->onDelete('cascade');
        });
    }
};
