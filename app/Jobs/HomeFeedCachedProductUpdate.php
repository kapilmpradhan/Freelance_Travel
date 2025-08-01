<?php

namespace App\Jobs;

use App\DTOs\HomeFeedProductFilter;
use App\Logging\Logger;
use App\Services\ProductCategoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class HomeFeedCachedProductUpdate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Logger::info('Updating home feed cached products');

        $keys = Redis::keys('home_feed_product:*');

        foreach ($keys as $key) {
            Logger::info('Processing key: ' . $key);

            // Extracting parts from the key to create a HomeFeedProductFilter
            // Assuming the key format is 'home_feed_product:<agentBranchCode>_country_<countryId>_<filterBy>'
            // Example: 'home_feed_product:FTX_country_20_experience'
            // This will split the key into parts and reverse them to get the correct order
            $parts = explode(':', $key);
            $subParts = array_reverse(explode('_', $parts[1]));
            $filterBy = $subParts[0] ?? null;
            $countryId = $subParts[1] ?? null;
            $agentBranchCode = $subParts[3] ?? null;

            $productFilter = HomeFeedProductFilter::fromJob($agentBranchCode, $countryId, $filterBy);

            if ($countryId && $filterBy) {
                ProductCategoryService::getProductByCategoriesWithLabelV2($productFilter, true);
            }
        }
        Logger::info('Home feed cached products update completed');
        return;
    }
}
