<?php

namespace App\Jobs;

use App\DTOs\ItemType;
use App\Enums\AgentBranchCode;
use App\Logging\Logger;
use App\Models\Quote;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FcmService;
use App\Services\IEmailService;
use App\Services\ServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ShareQuoteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $shareQuote;
    public $agentType;
    public $itemType;
    public function __construct($shareQuote, $agentType, $itemType)
    {
        $this->shareQuote = $shareQuote;
        $this->agentType = $agentType;
        $this->itemType = $itemType;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService, FcmService $fcmService): void
    {
        Logger::debug('Share quote job started', [
            'log_file' => config('logging.log_files.quote'),
            'quote_id' => $this->shareQuote->quote_id,
            'shared_to_email' => $this->shareQuote->shared_to_email,
            'action' => 'share_quote_started',
        ]);

        $quote = Quote::where('id', $this->shareQuote->quote_id)->first();
        $inviter = User::where('uuid', $this->shareQuote->shared_by_user_id)->first();
        $invitee = User::where('email', $this->shareQuote->shared_to_email)->first();
        if ($invitee) {
            $inviteeRegistered = true;
            $inviteeName = $invitee->first_name ?
                $invitee->first_name . ' ' . $invitee->last_name :
                $invitee->nickname;
        } else {
            $inviteeRegistered = false;
            $inviteeName = null;
        }

        $inviterName = $inviter->first_name ?
            $inviter->first_name . ' ' . $inviter->last_name :
            $inviter->nickname;

        $templateId = $this->agentType->platform === AgentBranchCode::DEFAULT
            ? config('vars.share_quote_template_id')
            : config('vars.peterpans_share_quote_template_id');

        $this->itemType->forPreview = true;
        $postOrderResponse = BookingService::basePostOrder(
            userId: $inviter->uuid,
            intent: 'pay-now',
            pointsApplied: null,
            commissionApplied: null,
            itemType: $this->itemType,
            agentType: $this->agentType
        );

        if ($postOrderResponse->isError()) {
            throw new ServiceException('Unable to get quote link for share quote', $postOrderResponse->data);
        }

        $postOrderData = $postOrderResponse->data;

        $mailData = [
            'to' => [
                [
                    'email' => $this->shareQuote->shared_to_email
                ]
            ],
            'templateId' => (int) $templateId,
            'params' => [
                'inviterName' => $inviterName,
                'inviteeName' => $inviteeName,
                'inviteeRegistered' => $inviteeRegistered,
                'quoteName' => $quote->title,
                'system' => $this->agentType->platform == AgentBranchCode::DEFAULT ? 'FreelanceTravel' : 'PeterPans',
                'redirectUrl' => $postOrderData['quoteUrl']
            ]
        ];
        $emailService->sendMail($mailData);

        if ($invitee) {
            $tokens = $invitee->fcmTokens($this->agentType->platform);
            $pushNotificationData = [
                'title' => "Quote Shared",
                'body' => "{$quote->title} has been shared with you.",
                'path' => "/quotes/shared/{$this->shareQuote->id}"
            ];

            foreach ($tokens as $token) {
                $fcmService->sendNotification(
                    data: $pushNotificationData,
                    token: $token
                );
            }
        }

        Logger::debug('Share quote job completed', [
            'log_file' => config('logging.log_files.quote'),
            'quote_id' => $this->shareQuote->quote_id,
            'shared_to_email' => $this->shareQuote->shared_to_email,
            'invitee_registered' => $inviteeRegistered,
            'action' => 'share_quote_completed',
        ]);
        return;
    }
}
