<?php

use Illuminate\Database\Migrations\Migration;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip seeding in testing environment (requires external API calls)
        if (app()->environment('testing')) {
            return;
        }

        DB::table('product_categories')->truncate();
        (new ProductCategorySeeder())->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('product_categories')->truncate();
    }
};
