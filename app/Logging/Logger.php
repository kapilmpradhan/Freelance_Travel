<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;

class Logger
{
    public static function info($message, $data = null)
    {
        Log::stack(['console', 'single'])->info($message, $data);
    }

    public static function debug($message, $data = null)
    {
        $data = [
            "message" => $message,
            "data" => $data,
        ];
        Log::stack(['console', 'single'])->debug(json_encode($data));
    }

    public static function error($message, $exception = null, $extra = null)
    {
        Log::stack(['console', 'retack', 'single'])->error(
            $message,
            ['exception' => $exception ?? $message, 'extra' => $extra ?? []]
        );
    }
}
