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

class SendShareMailJob
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
        Logger::debug('Processing share mail job', [
            'log_file' => config('logging.log_files.email'),
            'agent_email' => $this->data['agent']['emailAddress'] ?? null,
            'redeemer_email' => $this->data['redeemers'][0]['emailAddress'] ?? null,
            'action' => 'share_mail_job_start',
        ]);

        try {
            $agentData = [
                'sender' => [
                    'email' => config('vars.mail_from_address')
                ],
                'to' => [
                    [
                        'email' => $this->data['agent']['emailAddress']
                    ]
                ],
                'subject' => "Payment link sent to customer.",
                'htmlContent' => view('email.agentShare')->render(),
            ];

            $customerData = [
                'sender' => [
                    'email' => config('vars.mail_from_address')
                ],
                'to' => [
                    [
                        'email' => $this->data['redeemers'][0]['emailAddress']
                    ]
                ],
                'subject' => "Freelance travel payment link.",
                'htmlContent' => view('email.emailShare', ["data" => $this->data])->render(),
            ];


            $emailService->sendMail($agentData);
            $emailService->sendMail($customerData);


            Logger::debug('Payment email sent to agent and redeemer', [
                'log_file' => config('logging.log_files.email'),
                'agent_email' => $this->data['agent']['emailAddress'] ?? null,
                'redeemer_email' => $this->data['redeemers'][0]['emailAddress'] ?? null,
                'action' => 'share_mail_job_sent',
            ]);
        } catch (Exception $e) {
            Logger::error('Failed to send share email', $e, data: [
                'log_file' => config('logging.log_files.email'),
                'action' => 'share_mail_job_exception',
            ]);
            throw $e;
        }
    }
}
