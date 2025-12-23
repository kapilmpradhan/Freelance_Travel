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
        Logger::debug('Updating home feed cached products', [
            'log_file' => config('logging.log_files.products'),
            'action' => 'home_feed_cache_update_start',
        ]);

        $keys = Redis::keys('home_feed_product:*');

        foreach ($keys as $key) {
            Logger::debug('Processing home feed cache key', [
                'log_file' => config('logging.log_files.products'),
                'key' => $key,
                'action' => 'home_feed_cache_processing_key',
            ]);

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
        Logger::debug('Home feed cached products update completed', [
            'log_file' => config('logging.log_files.products'),
            'keys_processed' => count($keys),
            'action' => 'home_feed_cache_update_complete',
        ]);
        return;
    }
}
