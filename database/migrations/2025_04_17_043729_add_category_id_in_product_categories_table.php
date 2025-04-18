<?php

use App\Services\TdmsService;
use App\Services\UserAgentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * New fields category_id and category_type are added to the product_categories table.
     * The category_id is populated with the ID of the category from the TDMS API.
     * The category_type is populated with the type of the category (e.g., accommodation, transport, activities).
     */
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('category_id')->nullable();
            $table->string('category_type')->nullable();
        });

        $agent = UserAgentService::getDefaultAgentToken();
        $agentToken = $agent->data['access_token'];
        // Populate the new columns with existing data
        $categories = DB::table('product_categories')->get();
            
        $categoryTypes = ['accommodation', 'transport', 'activities'];
        foreach ($categoryTypes as $categoryType) {
            $getCategoriesResponse = TdmsService::getCategoriesByType(
                type: $categoryType,
                agentToken: $agentToken
            );

            if ($getCategoriesResponse->isError()) {
                return;
            }

            $categoriesData = $getCategoriesResponse->data;
            foreach ($categoriesData as $category) {
                    $existingCategory = $categories->where('category', $category['text'])->first();
                    if ($existingCategory) {
                        if ($existingCategory) {
                            DB::table('product_categories')
                                ->where('id', $existingCategory->id)
                                ->update([
                                    'category_id' => $category['id'],
                                    'category_type' => $categoryType,
                                ]);
                        }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('category_type');
        });
    }
};
