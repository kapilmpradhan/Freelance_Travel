<?php

namespace App\Jobs;

use Exception;
use App\Models\Product;
use App\Services\ProductService;
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

    protected $user_id;
    protected $productId;

    /**
     * Create a new job instance.
     */
    public function __construct($productId)
    {
        $this->productId = $productId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $response = ProductService::getProductDetailsFromApi($this->productId);

            if (!$response || !$response['results']) {
                echo 'Unable to cache product. Product not found. Product id:' . $this->productId;
            } else {
                $productDetailsFromApi = $response['results'][0];

                $data = [
                    "product_id" => $this->productId,
                    "json" => $productDetailsFromApi
                ];

                Product::create($data);
                echo 'Product cached. Product id: ' . $this->productId;
            }
        } catch (Exception $e) {
            echo 'Unable to cache product ('
            . $this->productId
            . '). Exception: '
            . $e->getMessage();
        }
    }
}
