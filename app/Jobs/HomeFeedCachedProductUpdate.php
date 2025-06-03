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
            $parts = explode(':', $key);
            $countryId = explode('_', $parts[1])[1] ?? null;
            $filterBy = explode('_', $parts[1])[2] ?? null;

            $productFilter = HomeFeedProductFilter::fromJob($countryId, $filterBy);

            if ($countryId && $filterBy) {
                ProductCategoryService::getProductByCategoriesWithLabelV2($productFilter, true);
            }
        }
        Logger::info('Home feed cached products update completed');
        return;
    }
}
