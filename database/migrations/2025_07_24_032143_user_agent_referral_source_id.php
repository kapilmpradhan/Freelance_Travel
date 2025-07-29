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
            $table->integer('referral_source_id')->nullable()->after('branch_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_agents', function (Blueprint $table) {
            $table->dropColumn('referral_source_id');
        });
    }
};
