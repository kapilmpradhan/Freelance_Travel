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
        Schema::table('cart_customer_details', function (Blueprint $table) {
            $table->string("country_code")->default("036");
        });

        DB::table('cart_customer_details')
            ->whereNull('country_code')
            ->update(['country_code' => "036"]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_customer_details', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
