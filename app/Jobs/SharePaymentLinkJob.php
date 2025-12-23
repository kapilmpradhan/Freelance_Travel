<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
use App\Logging\Logger;
use App\Models\Quote;
use App\Models\SharedPayment;
use App\Services\IEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SharePaymentLinkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var SharedPayment
     */
    public $sharedPayment;
    public $platform;
    public function __construct(SharedPayment $sharedPayment, $platform)
    {
        $this->sharedPayment = $sharedPayment;
        $this->platform = $platform;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService): void
    {
        $quoteId = $this->sharedPayment->quote_id;
        $name = $this->sharedPayment->name;
        $email = $this->sharedPayment->email;
        $paymentLink = config('app.url') . "/api/quote/{$this->sharedPayment->quote_id}/payment";

        Logger::debug('Processing share payment link job', [
            'log_file' => config('logging.log_files.payment'),
            'quote_id' => $quoteId,
            'email' => $email,
            'action' => 'share_payment_link_start',
        ]);

        $quote = Quote::where('id', $quoteId)->first();

        $templateIdVarName = $this->platform == AgentBranchCode::PETERPANS
            ? 'peterpans_share_payment_template_id'
            : 'share_payment_template_id';

        $data = [
            'to' => [
                [
                    'email' => $email,
                ]
            ],
            'templateId' => (int) config('vars.' . $templateIdVarName),
            'params' => [
                'receiverName' => $name,
                'quoteName' => $quote->title,
                'paymentLink' => $paymentLink
            ],
        ];

        $emailService->sendMail($data);

        Logger::debug('Share payment link email sent', [
            'log_file' => config('logging.log_files.payment'),
            'quote_id' => $quoteId,
            'email' => $email,
            'action' => 'share_payment_link_sent',
        ]);
    }
}
