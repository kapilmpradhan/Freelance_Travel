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
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable();
            $table->bigInteger('phone_number')->nullable();
            $table->integer('post_code')->nullable();
            $table->string('country')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_number');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('post_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
