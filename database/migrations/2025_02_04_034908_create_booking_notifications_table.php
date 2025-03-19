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
        Schema::create('booking_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_order_id')->constrained('user_orders')->onDelete('cascade');
            $table->foreignId('cart_item_id')->unique()->constrained('cart_items')->onDelete('cascade');
            $table->date('booking_date');
            $table->time('booking_time')->nullable();
            $table->string('notify_to_email');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_notifications');
    }
};
