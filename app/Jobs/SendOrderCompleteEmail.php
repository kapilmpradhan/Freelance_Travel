<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\IEmailService;
use App\Logging\Logger;
use App\Services\BrevoEmailService;
use App\Services\TdmsService;
use Carbon\Carbon;

class SendOrderCompleteEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $data;
    protected $platform;
    protected $agentType;

    public function __construct($data, $platform, $agentType)
    {
        $this->data = $data;
        $this->platform = $platform;
        $this->agentType = $agentType;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService)
    {
        $sender = BrevoEmailService::platformSenderDetail($this->platform);
        $this->data['platform'] = $sender['name'];
        $bookingReference = $this->data['bookingReference'];

        $agentToken = $this->agentType->agent->access_token;
        $getCustomerDetails = TdmsService::customerOrderHistory(
            agentToken: $agentToken,
            sinceDate: Carbon::yesterday()
        );
        $customerOrders = $getCustomerDetails->data;

        $requiredOrder = array_filter($customerOrders, function ($order) use ($bookingReference) {
            return isset($order['bookingReference']) && $order['bookingReference'] === $bookingReference;
        })[0];

        $templateIdVarName = $this->platform == AgentBranchCode::PETERPANS && !$this->agentType->isCommissionAgent
            ? 'peterpans_order_complete_template_id'
            : 'order_complete_template_id';

        $this->data['totalCharged'] = round($requiredOrder['paidAmount'], 2);
        $this->data['isCommissionAgent'] = $this->agentType->isCommissionAgent;
        $customerData = [
            'to' => [
                [
                    'email' => $this->data['redeemers'][0]['emailAddress']
                ]
            ],
            'templateId' => (int) config('vars.' . $templateIdVarName),
            'params' => $this->data
        ];

        $emailService->sendMail($customerData);

        Logger::info('Order complete email sent.');
    }
}
