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
        Schema::table('cart_items', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('booking_date');
            $table->integer('days')->nullable()->after('start_date');
            $table->integer('selected_index')->nullable()->after('days');
            $table->json('availability')->nullable()->after('selected_index');
            $table->timestamp('availability_last_updated_at')->nullable()->after('availability');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('start_date');
            $table->dropColumn('days');
            $table->dropColumn('selected_index');
            $table->dropColumn('availability');
            $table->dropColumn('availability_last_updated_at');
        });
    }
};
