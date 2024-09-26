<?php

namespace App\Jobs;

use App\Mail\LogMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendLogMail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $email;
    protected $mail;
    protected $title;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($email, $mail, $title)
    {
        $this->email = $email;
        $this->mail = $mail;
        $this->title = $title;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $email = new LogMail($this->mail, $this->title);
        Mail::to($this->email)->send($email);
    }
}
