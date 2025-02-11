<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Database\Seeders\ProductCategorySeeder;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        (new ProductCategorySeeder())->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->truncate();
        });
    }
};
