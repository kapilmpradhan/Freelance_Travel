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
        Schema::create('user_orders', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('booking_reference')->unique(); // Unique booking reference
            $table->json('cart_item_ids'); // Stores array of cart item IDs
            $table->uuid('user_id'); // Foreign key to users table
            $table->json('request_data'); // Stores request data as JSON
            $table->json('response_data'); // Stores response data as JSON
            $table->timestamps(); // created_at and updated_at columns

            // Add foreign key constraint
            $table->foreign('user_id')->references('uuid')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_orders');
    }
};
