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
        Schema::table('user_orders', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->change();
        });

        // Update NULL values to false
        DB::table('user_orders')->whereNull('is_paid')->update(['is_paid' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_orders', function (Blueprint $table) {
            //
        });
    }
};
