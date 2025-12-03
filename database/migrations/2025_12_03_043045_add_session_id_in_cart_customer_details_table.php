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
            $table->dropForeign(['user_id']);
        });

        Schema::table('cart_customer_details', function (Blueprint $table) {
            $table->string('user_id')->nullable()->change();
            $table->string('session_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_customer_details', function (Blueprint $table) {
            $table->string('user_id')->nullable(false)->change();
            $table->dropColumn('session_id');
        });

        Schema::table('cart_customer_details', function (Blueprint $table) {
            $table->foreign('user_id')->references('uuid')->on('users')->onDelete('cascade');
        });
    }
};
