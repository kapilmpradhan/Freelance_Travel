<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\IEmailService;
use App\Logging\Logger;

class SendOrderCompleteEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService)
    {
        $customerData = [
            'sender' => [
                'email' => config('vars.mail_from_address')
            ],
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
