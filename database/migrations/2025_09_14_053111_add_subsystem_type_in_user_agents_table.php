<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_agents', function (Blueprint $table) {
            $table->string('subsystem_type')->nullable();
        });

        // Populate the new 'subsystem_type' field for existing records
        DB::table('user_agents')
            ->whereNotIn('branch_code', ['FTX', 'PTX'])
            ->update(['subsystem_type' => 'FIT']);

        DB::table('user_agents')
            ->whereIn('branch_code', ['FTX', 'PTX'])
            ->update(['subsystem_type' => 'FTA']);

        Schema::table('user_agents', function (Blueprint $table) {
            $table->string('subsystem_type')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_agents', function (Blueprint $table) {
            $table->dropColumn('subsystem_type');
        });
    }
};
