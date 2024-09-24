<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendLogMail;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function log(Request $request)
    {
        $title = $request->title ?? "Send log";
        $mail = $request->mail ?? "Hello World";
        $email = $request->email ?? "support@freelancetravel.com";

        dispatch(new SendLogMail($email, $mail, $title));
        return "ok";
    }
}
