<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->date('booking_date')->nullable();
            $table->string('time_id')->nullable();
        });

        // Migrate data from booking_datetime to booking_date and time_id
        DB::table('cart_items')->get()->each(function ($cartItem) {
            if (!empty($cartItem->booking_datetime)) {
                $datetime = new DateTime($cartItem->booking_datetime);

                DB::table('cart_items')->where('id', $cartItem->id)->update([
                    'booking_date' => $datetime->format('Y-m-d'),
                    'time_id' => $datetime->format('H_i_s'),
                ]);
            }
        });

        // Drop the old column
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('booking_datetime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dateTime('booking_datetime')->nullable();
        });

        // Restore the booking_datetime field from booking_date and time_id
        DB::table('cart_items')->get()->each(function ($cartItem) {
            if (!empty($cartItem->booking_date) && !empty($cartItem->time_id)) {
                $datetime = DateTime::createFromFormat(
                    'Y-m-d H_i_s', 
                    $cartItem->booking_date . ' ' . $cartItem->time_id
                );

                DB::table('cart_items')->where('id', $cartItem->id)->update([
                    'booking_datetime' => $datetime->format('Y-m-d H:i:s'),
                ]);
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['booking_date', 'time_id']);
        });
    }
};
