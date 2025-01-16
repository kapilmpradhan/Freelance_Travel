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
            $table->boolean('is_active')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->string('branch_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_agents', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('user_agents', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
        });

        Schema::table('user_agents', function (Blueprint $table) {
            $table->dropColumn('branch_code');
        });
    }
};
