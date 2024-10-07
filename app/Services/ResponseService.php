<?php

namespace App\Services;

class ResponseService
{
    public function sendError($error, $errorMessage = '', $code = 404)
    {
        $response = [
            'success' => false,
            'title' => $error,
            'code' => $code
        ];

        if (!$errorMessage) {
            $response['message'] = $errorMessage;
        }

        return response()->json($response, $code);
    }


    public function sendResponse($result, $message)
    {
        $response = [
            'success' => true,
            'data' => $result,
            'message' => $message,
            'code' => 200
        ];

        return response()->json($response, 200);
    }
}
