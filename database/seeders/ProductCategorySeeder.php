<?php

namespace Database\Seeders;

use League\Csv\Reader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Logging\Logger;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csv = Reader::createFromPath(database_path('seeders/productCategories.csv'), 'r');
        $csv->setHeaderOffset(0); // Assuming first row is the header

        $data = [];
        foreach ($csv as $row) {
            $data[] = [
                'category' => $row['category'],
                'label' => $row['label'],
            ];
        }

        // Insert the data into the 'product_categories' table in chunks to improve performance
        DB::table('product_categories')->insertOrIgnore($data);

        Logger::info('Product category seeding complete.');
    }
}
