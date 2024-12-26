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
        Schema::create('agent_details', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
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

        // Populate agent_details from agent_tokens
        DB::table('agent_tokens')->get()->each(function ($token) {
            DB::table('agent_details')->insert([
                'email' => $token->username,
                'access_token' => $token->access_token,
                'expires_in' => $token->expires_in,
                'token_type' => $token->token_type,
                'scope' => $token->scope,
                'bank_bsb' => $token->bank_bsb ?? null,
                'bank_account' => $token->bank_account ?? null,
                'bank_country_short_code' => $token->bank_country_short_code ?? null,
                'business_number' => $token->business_number ?? null,
                'trading_name' => $token->trading_name ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_details');
    }
};
