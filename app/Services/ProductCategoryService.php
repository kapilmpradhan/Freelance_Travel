<?php

namespace App\Services;

use App\Models\ProductCategory;

class ProductCategoryService
{
    public static function getCategories()
    {
        // Fetch all categories that have a non-null category value
        $categories = ProductCategory::whereNotNull('category')
            ->get();

        // Initialize array to store formatted data
        $formattedData = [];
        foreach ($categories as $category) {
            // Create a new array for label if it doesn't exist
            if (!isset($formattedData[$category->label])) {
                $formattedData[$category->label] = [];
            }
            // Add category to the corresponding label array
            $formattedData[$category->label][] = $category->category;
        }

        // Return formatted response with grouped categories
        return ServiceResponse::success(
            message: 'Product categories',
            data: $formattedData
        );
    }
}
