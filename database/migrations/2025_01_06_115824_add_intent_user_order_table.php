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
        Schema::table('user_orders', function (Blueprint $table) {
            $table->boolean('is_paid')->nullable();
            $table->string('intent')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_orders', function (Blueprint $table) {
            $table->dropColumn('is_paid');
            $table->dropColumn('intent');
        });
    }
};
