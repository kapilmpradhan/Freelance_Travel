<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BookingCancelRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('booking_cancel_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('bookingReference')->nullable();
            $table->string('voucherNumber')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('booking_cancel_requests');
    }
}
