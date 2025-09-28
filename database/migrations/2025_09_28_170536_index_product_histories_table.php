<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_histories', function (Blueprint $table) {
            $table->index(['tdms_product_id', 'version'], 'tdms_product_version_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_histories', function (Blueprint $table) {
            $table->dropIndex('tdms_product_version_index');
        });
    }
};
