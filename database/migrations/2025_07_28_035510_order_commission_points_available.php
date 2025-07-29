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
        Schema::table('user_order_commissions', function (Blueprint $table) {
            $table->integer('points_available')->nullable()->after('percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_order_commissions', function (Blueprint $table) {
            $table->dropColumn('points_available');
        });
    }
};
