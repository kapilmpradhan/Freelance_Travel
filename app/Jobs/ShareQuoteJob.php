<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
use App\Logging\Logger;
use App\Models\Quote;
use App\Models\User;
use App\Services\FcmService;
use App\Services\IEmailService;
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
    public $platform;
    public function __construct($shareQuote, $platform)
    {
        $this->shareQuote = $shareQuote;
        $this->platform = $platform;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService, FcmService $fcmService): void
    {
        Logger::info("ShareQuoteJob started");

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

        $webUrl = $this->platform === AgentBranchCode::DEFAULT
            ? config('app.web_url')
            : config('app.web_url_peterpans');

        $templateId = $this->platform === AgentBranchCode::DEFAULT
            ? config('vars.share_quote_template_id')
            : config('vars.peterpans_share_quote_template_id');

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
                'system' => $this->platform == AgentBranchCode::DEFAULT ? 'FreelanceTravel' : 'PeterPans',
                'redirectUrl' => $webUrl . "/quotes/{$quote->id}?isPending=true"
            ]
        ];
        $emailService->sendMail($mailData);

        if ($invitee) {
            $tokens = $invitee->fcmTokens($this->platform);
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

        Logger::info("ShareQuoteJob complete");
        return;
    }
}
