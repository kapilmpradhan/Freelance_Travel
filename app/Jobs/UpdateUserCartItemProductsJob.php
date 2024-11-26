<?php

namespace App\Jobs;

use Exception;
use App\Models\Product;
use App\Models\CartItem;
use App\Services\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateUserCartItemProductsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $cartItems = CartItem::where('user_id', $this->userId)->get();
            foreach ($cartItems as $item) {
                $productId = $item['product_id'];
                try {
                    $response = ProductService::getProductDetailsFromApi($productId);

                    if (!$response || !$response['results']) {
                        $data = [
                            "product_id" => $productId,
                            "json" => []
                        ];
                    } else {
                        $productDetailsFromApi = $response['results'][0];

                        $data = [
                            "product_id" => $productId,
                            "json" => $productDetailsFromApi
                        ];
                    }

                    Product::create($data);
                    echo 'Product cached. Product id: ' . $productId;
                } catch (Exception $e) {
                    echo 'Unable to cache product ('
                    . $productId
                    . '). Exception: '
                    . $e->getMessage();
                }
            }
        } catch (Exception $e) {
            echo 'Unable to cache product ('
            . $productId
            . '). Exception: '
            . $e->getMessage();
        }
    }
}
