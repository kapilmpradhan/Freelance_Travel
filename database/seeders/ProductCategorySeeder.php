<?php

namespace Database\Seeders;

use League\Csv\Reader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Logging\Logger;
use App\Services\TdmsService;
use App\Services\UserAgentService;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csv = Reader::createFromPath(database_path('seeders/updatedProductCategories.csv'), 'r');
        $csv->setHeaderOffset(0); // Assuming first row is the header

        $parentCategories = ['accommodation', 'transport', 'activities'];
        $agentToken = UserAgentService::getDefaultAgentToken()->data['access_token'];
        $data = [];
        foreach ($parentCategories as $parentCateogry) {
            $categoriesResponse = TdmsService::getCategoriesOfAType($parentCateogry, $agentToken);
            if ($categoriesResponse->isError()) {
                Logger::error("Failed to fetch categories for type: {$parentCateogry}");
                continue;
            }
            $categoriesData = $categoriesResponse->data;
            foreach ($categoriesData as $category) {
                foreach ($csv as $row) {
                    if ($row['category'] === $category['text']) {
                        $data[] = [
                            'category' => $row['category'],
                            'label' => $row['label'],
                            'category_id' => $category['id'],
                            'category_type' => $parentCateogry,
                            'type' => $row['type'],
                        ];
                    }
                }
            }
        }

        // Clear all existing data
        DB::table('product_categories')->truncate();

        // Insert the data into the 'product_categories' table
        DB::table('product_categories')->insertOrIgnore($data);

        Logger::info('Product category seeding complete.');
    }
}
