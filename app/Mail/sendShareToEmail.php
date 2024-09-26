<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendShareToEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    protected $data;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $agentName = @$this->data->agent->name ?? "Freelance Travel";
        $agentEmail = @$this->data->agent->email ?? "support@freelance.com";
        return $this->subject("Your Freelance Travel Secure Payment Link From $agentName")
            ->replyTo($agentEmail)
            ->cc($agentEmail)
            ->view('email.emailShare', ['data' => $this->data]);
    }
}
