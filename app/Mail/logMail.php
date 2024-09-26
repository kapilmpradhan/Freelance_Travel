<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LogMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    protected $mail;
    protected $title;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($mail, $title)
    {
        $this->mail = $mail;
        $this->title = $title;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->title)
            ->view('email.logMail', ['mail' => $this->mail]);
    }
}
