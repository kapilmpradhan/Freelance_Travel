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
        Schema::table('agent_tokens', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
            $table->string('type')->default('user');
            $table->string('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, change the 'user_id' column to not nullable
        Schema::table('agent_tokens', function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
        });

        // Then, drop the 'type' and 'password' columns in separate calls
        Schema::table('agent_tokens', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('agent_tokens', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }

};
