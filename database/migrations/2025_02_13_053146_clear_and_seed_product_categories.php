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
        // Swap the column names
        Schema::table('product_categories', function (Blueprint $table) {
            $table->renameColumn('category', 'temp_column');
            $table->renameColumn('label', 'category');
            $table->renameColumn('temp_column', 'label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Swap the column names back
        Schema::table('product_categories', function (Blueprint $table) {
            $table->renameColumn('label', 'temp_column');
            $table->renameColumn('category', 'label');
            $table->renameColumn('temp_column', 'category');
        });
    }
};
