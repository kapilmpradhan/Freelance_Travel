<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class logMail extends Mailable
{
    use Queueable, SerializesModels;

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
