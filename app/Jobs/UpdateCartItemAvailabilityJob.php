<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Services\CartItemService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateCartItemAvailabilityJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $item;
    protected $agent;

    /**
     * Create a new job instance.
     */
    public function __construct($item, $agent)
    {
        $this->item = $item;
        $this->agent = $agent;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Logger::info('Updating availability for item: ' . $this->item->id);

        $availabilityResponse = CartItemService::getItemAvailability($this->item, $this->agent);
        if ($availabilityResponse->isError()) {
            Logger::debug(
                message: 'Error updating availability for item: ' . $this->item->id,
                data: $availabilityResponse->data
            );
        } else {
            $this->item->availability = $availabilityResponse->data;
            $this->item->availability_last_updated_at = Carbon::now();
            $fillableFields = $this->item->getFillable(); // fields allowed in DB

            foreach ($this->item->getAttributes() as $key => $value) {
                if (!in_array($key, $fillableFields)) {
                    unset($this->item->$key);
                }
            }

            $this->item->save();
        }
    }
}
