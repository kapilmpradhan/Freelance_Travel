<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;

class Logger
{
    public static function writeToFile(array $logData)
    {
        $logData = array_merge(
            ['date_time' => date('Y-m-d H:i:s')],
            $logData
        );

        if (isset($logData['log_file'])) {
            $logFile = $logData['log_file'];
            unset($logData['log_file']);
        }

        $path = storage_path(
            'logs/' .
            date('Y-m-d') .
            '/' .
            $logFile
        );

        // Ensure the directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fileExists = file_exists($path);
        $file = fopen($path, 'a');

        $header = [];
        $values = [];
        foreach ($logData as $key => $value) {
            $header[] = ucwords(str_replace('_', ' ', $key));
            $values[] = $value;
        }

        if (!isset($logData['exception'])) {
            $header[] = 'Exception';
            $values[] = 'N/A';
        }
        if (!$fileExists) {
            fputcsv($file, $header);
        }

        fputcsv($file, $values);
        fclose($file);
    }

    public static function info($message, $data = null, bool $write = false)
    {
        Log::stack(['console', 'single'])->info($message, $data ?? []);
        if ($write) {
            $data['status'] = 'Success';
            self::writeToFile($data);
        }
    }

    public static function debug($message, $data = null)
    {
        $logPayload = [
            "message" => $message,
            "data" => $data,
        ];
        Log::stack(['console', 'single'])->debug(json_encode($logPayload ?? []));
        // Debug level always writes to file when log_file is provided
        if (\is_array($data) && isset($data['log_file'])) {
            $data['status'] = 'Debug';
            self::writeToFile($data);
        }
    }

    public static function error($message, $exception = null, $extra = null, $data = null)
    {
        Log::stack(['console', 'retack', 'single'])->error(
            $message,
            ['exception' => $exception ?? $message, 'extra' => $extra ?? []]
        );
        // Write to file if flag is enabled or data contains log_file
        $shouldWrite = config('logging.write_errors_to_file', false);
        if ($shouldWrite && \is_array($data) && isset($data['log_file'])) {
            $data['status'] = 'Error';
            $data['exception'] = $exception ? $exception->getMessage() : $message;
            self::writeToFile($data);
        }
    }

    public static function exception($message, $data = [], $exception = null)
    {
        Log::stack(['console', 'retack', 'single'])->critical(
            $message,
            ['exception' => $exception, 'data' => $data]
        );
        // Write to file if flag is enabled or data contains log_file
        $shouldWrite = config('logging.write_errors_to_file', false);
        if ($shouldWrite && \is_array($data) && isset($data['log_file'])) {
            $data['status'] = 'Exception';
            $data['exception'] = $exception ? $exception->getMessage() : $message;
            self::writeToFile($data);
        }
    }
}
