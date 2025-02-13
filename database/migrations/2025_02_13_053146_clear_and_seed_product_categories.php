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
        Schema::table('product_categories', function (Blueprint $table) {
            $table->renameColumn('category', 'temp_category');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->renameColumn('label', 'category');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->renameColumn('temp_category', 'label');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->renameColumn('category', 'temp_category');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->renameColumn('label', 'category');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->renameColumn('temp_category', 'label');
        });
    }
};
