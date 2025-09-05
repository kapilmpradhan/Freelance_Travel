<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Services\CartItemService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CacheProductJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $product;
    protected $latestProduct;
    protected $batchNumber;

    /**
     * Create a new job instance.
     */
    public function __construct($product, $latestProduct, $batchNumber)
    {
        $this->product = $product;
        $this->latestProduct = $latestProduct;
        $this->batchNumber = $batchNumber;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Logger::info('Caching batch number ' . $this->batchNumber);
        CartItemService::cacheProduct($this->product, $this->latestProduct);
    }
}
