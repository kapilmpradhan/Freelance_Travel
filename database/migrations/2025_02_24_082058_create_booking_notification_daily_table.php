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
        Schema::create('booking_notification_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_notification_id')->constrained('booking_notifications')->onDelete('cascade');
            $table->foreignId('user_order_id')->constrained('user_orders')->onDelete('cascade');
            $table->foreignId('cart_item_id')->unique()->constrained('cart_items')->onDelete('cascade');
            $table->date('booking_time')->nullable();
            $table->string('notify_to_email');
            $table->boolean('is_notified');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_notification_daily');
    }
};
