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
        Schema::create('user_order_commissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->index();
            $table->integer('user_order_id')-> nullable();
            $table->string('agent_branch');
            $table->boolean('is_cart')->default(false);
            $table->string('quote_id')->nullable();
            $table->boolean('is_direct_purchase')->default(false);
            $table->decimal('percentage', 5, 2)->nullable()->default(null);
            $table->foreign('user_id')->references('uuid')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_order_commissions');
    }
};
