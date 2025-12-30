<?php

use App\Jobs\HomeFeedProductByCategories;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip in testing environment (requires external API calls)
        if (app()->environment('testing')) {
            return;
        }

        HomeFeedProductByCategories::dispatch();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
