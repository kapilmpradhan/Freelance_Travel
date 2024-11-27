<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;

class Logger
{
    public static function info($message)
    {
        Log::channel('console')->info($message);
    }

    public static function debug($message)
    {
        Log::channel('console')->debug($message);
    }

    public static function error($message, $exception = null)
    {
        Log::stack(['console', 'retack'])->error($message, ['exception' => $exception]);
    }
}
