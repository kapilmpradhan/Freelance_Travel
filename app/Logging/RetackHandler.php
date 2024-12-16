<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Handler\AbstractProcessingHandler;

class RetackHandler extends AbstractProcessingHandler
{
    protected $client;
    protected $apiUrl;

    public function __construct($level = \Monolog\Level::Debug, $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $exception = $record['context']['exception'] ?? null;

        $logData = [
            'title' => $record['message'],
            'stack_trace' => $exception instanceof \Throwable
                            ? $exception->getTraceAsString()
                            : 'No stack trace available'
        ];

        // Fetch the user context (from session, auth user, etc.)
        $userContext = $this->getUserContext();

        try {
            $requestUrl = config('vars.retack_error_logging_url');

            // Initialize cURL
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, "{$requestUrl}");
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                "ENV-KEY: " . config('vars.retack_env_key'),
                "Content-Type: application/json",
                "HTTP_X_USER_CONTEXT: {$userContext}",
            ));

            // Set the HTTP method and body
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($logData));

            // Set options for the response
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

            curl_exec($curl);

            // Get cURL info
            $curlInfo = curl_getinfo($curl);
            $statusCode = $curlInfo['http_code'];

            if ($statusCode == 404) {
                Logger::info("Logging to retack failed. Invalid env key or body type.");
            } elseif ($statusCode == 500) {
                Logger::info("Logging to retack failed. Retack server error.");
            }
        } catch (\Exception $e) {
            Logger::info('Logging to retack failed. Error: ' . $e->getMessage());
        }
    }

    protected function getUserContext()
    {
        if (auth()->check()) {
            return auth()->user()->uuid;
        }

        return 'anonymous';
    }
}
