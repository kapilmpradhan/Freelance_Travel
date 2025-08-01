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
        Schema::table('booking_notifications', function (Blueprint $table) {
            $table->string('platform')->default('FTX');
        });

        Schema::table('booking_notification_daily', function (Blueprint $table) {
            $table->string('platform')->default('FTX');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_notifications', function (Blueprint $table) {
            $table->dropColumn('platform');
        });

        Schema::table('booking_notification_daily', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
