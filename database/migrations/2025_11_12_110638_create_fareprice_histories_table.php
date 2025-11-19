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
        Schema::create('fareprice_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('tdms_product_id');
            $table->json('json');
            $table->char('agent_branch');
            $table->integer('version');
            $table->timestamps();

            // Unique index for product + branch + version
            $table->unique(['tdms_product_id', 'agent_branch', 'version'], 'fareprices_tdms_branch_unique');

            // Regular index for product + branch
            $table->index(['tdms_product_id', 'agent_branch'], 'fareprices_tdms_branch_index');

            // Index for all product + branch + version
            $table->index(['tdms_product_id', 'agent_branch', 'version'], 'fareprices_tdms_branch_version_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fareprice_histories');
    }
};
