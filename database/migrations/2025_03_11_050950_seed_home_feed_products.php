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
