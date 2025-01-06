<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;

class Logger
{
    public static function info($message)
    {
        Log::stack(['console', 'single'])->info($message);
    }

    public static function debug($message)
    {
        Log::stack(['console', 'single'])->debug($message);
    }

    public static function error($message, $exception = null, $extra = null)
    {
        Log::stack(['console', 'retack', 'single'])->error(
            $message,
            ['exception' => $exception ?? $message, 'extra' => $extra ?? []]
        );
    }
}
