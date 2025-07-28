<?php

namespace App\Console\Commands;

use App\Models\UserAgent;
use App\Services\TdmsService;
use App\Services\UserAgentService;
use Illuminate\Console\Command;

class TestTdmsCustomerOrderHistory extends Command
{
    protected $signature = 'app:test-tdms-customer-order-history {customerEmail}';

    /**
     * Execute the console command.
     */
    public function handle(TdmsService $tdmsService)
    {
        $email = $this->argument('customerEmail');

        $getAgentResponse = UserAgentService::getUserAgentIfExistsElseDefault(null);

        $userAgent = $getAgentResponse->data; /** @var UserAgent $userAgent */
        $response = $tdmsService->customerOrderHistory($userAgent->access_token, $email);

        dd($response);

        return 0;
    }
}
