<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Services\CartItemService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Schema;

class UpdateCartItemAvailabilityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $items;
    protected $agent;

    /**
     * Create a new job instance.
     */
    public function __construct($items, $agent)
    {
        $this->items = $items;
        $this->agent = $agent;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->items as $item) {
            $cartItem = $item['cartItem'];
            $product = $item['product'];
            Logger::info('Updating availability for item: ' . $cartItem->id);

            $availabilityResponse = CartItemService::getItemAvailability($cartItem, $product, $this->agent);
            if ($availabilityResponse->isError()) {
                Logger::error('Error updating availability for item: ' . $item->id);
            } else {
                $cartItem->availability = $availabilityResponse->data;
                $cartItem->availability_last_updated_at = Carbon::now();
                $fillableFields = $cartItem->getFillable(); // fields allowed in DB

                foreach ($cartItem->getAttributes() as $key => $value) {
                    if (!in_array($key, $fillableFields)) {
                        unset($cartItem->$key);
                    }
                }

                $cartItem->save();
            }
        }
    }
}
