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
        Schema::table('user_agents', function (Blueprint $table) {
            $table->integer('points_balance')->nullable();
            $table->integer('points_available')->nullable();
            $table->integer('points_multiplier')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_agents', function (Blueprint $table) {
            $table->dropColumn([
                'points_balance',
                'points_available',
                'points_multiplier',
            ]);
        });
    }
};
