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
        Schema::dropIfExists('user_agents');

        Schema::create('user_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('uuid')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->enum('type', ['profile', 'integration']);
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('access_token')->nullable();
            $table->enum('status', ['ok', 'expired']);
            $table->integer('expires_in')->nullable();
            $table->string('token_type')->nullable();
            $table->string('scope')->nullable();
            $table->string('bank_bsb')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_country_short_code')->nullable();
            $table->string('business_number')->nullable();
            $table->string('trading_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_agents');
    }
};
