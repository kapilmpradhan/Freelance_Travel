<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\IEmailService;
use App\Logging\Logger;
use App\Services\BrevoEmailService;
use App\Services\TdmsService;
use App\Services\UserAgentService;
use Carbon\Carbon;

class SendOrderCompleteEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $data;
    protected $platform;

    public function __construct($data, $platform)
    {
        $this->data = $data;
        $this->platform = $platform;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService)
    {
        $sender = BrevoEmailService::platformSenderDetail($this->platform);
        $this->data['platform'] = $sender['name'];
        $bookingReference = $this->data['bookingReference'];

        $agentResponse = UserAgentService::getUserAgentByBranch(substr($bookingReference, 0, 3));
        if ($agentResponse->isError()) {
            Logger::error('Agent not found');
            return;
        }

        $agentToken = $agentResponse->data->access_token;
        $getCustomerDetails = TdmsService::customerOrderHistory(
            agentToken: $agentToken,
            sinceDate: Carbon::yesterday()
        );
        $customerOrders = $getCustomerDetails->data;

        $requiredOrder = array_filter($customerOrders, function ($order) use ($bookingReference) {
            return isset($order['bookingReference']) && $order['bookingReference'] === $bookingReference;
        })[0];

        $this->data['totalCharged'] = round($requiredOrder['paidAmount'], 2);
        $customerData = [
            'sender' => $sender,
            'to' => [
                [
                    'email' => $this->data['redeemers'][0]['emailAddress']
                ]
            ],
            'templateId' => (int) config('vars.order_complete_template_id'),
            'params' => $this->data
        ];

        $emailService->sendMail($customerData);

        Logger::info('Order complete email sent.');
    }
}
