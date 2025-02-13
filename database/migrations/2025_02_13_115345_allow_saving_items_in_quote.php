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
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->onDelete('cascade');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->onDelete('cascade');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['email', 'json']);
            $table->uuid('user_id');
            $table->foreign('user_id')->references('uuid')->on('users')->onDelete('cascade');
            $table->string('title');
            $table->boolean('is_paid')->default(false);
            $table->foreignId('user_order_id')->nullable()->constrained('user_orders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_customer_details', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
            $table->dropColumn('quote_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
            $table->dropColumn('quote_id');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->string('email');
            $table->json('json');
            $table->dropForeign(['user_id']);
            $table->dropForeign(['user_order_id']);
            $table->dropColumn(['user_id', 'title', 'is_paid', 'user_order_id']);
        });
    }
};
