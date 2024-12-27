<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // From different version of products in table, it excludes latest version of product and only get ids of older versions. The retrieved products are then deleted.
        // Only the latest version of products are available in table after this query execution.
        
        DB::statement("
            DELETE FROM products
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT id 
                    FROM products
                    WHERE version = (
                        SELECT MAX(version)
                        FROM products AS sub
                        WHERE sub.tdms_product_id = products.tdms_product_id
                    )
                ) AS latest_versions
            )
        ");
        
        DB::table('products')
            ->where('version', '<>', '1.0')
            ->update(['version' => '1.0']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
