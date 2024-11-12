<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('uuid')->on('users')->onDelete('cascade');
            $table->string('username')->unique();
            $table->string('access_token', 255);
            $table->integer('expires_in');
            $table->string('token_type', 50);
            $table->string('scope', 50);
            $table->string('bank_bsb', 50)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_country_short_code', 5)->nullable();
            $table->string('business_number')->nullable()->nullable();
            $table->string('trading_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_tokens');
    }
};
