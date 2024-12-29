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
        Schema::create('cart_customer_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('email');
            $table->integer('customer_index');
            $table->string('postal_code')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('uuid')->on('users')->onDelete('cascade');
            // Unique constraint
            $table->unique(['user_id', 'customer_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_customer_details');
    }
};
