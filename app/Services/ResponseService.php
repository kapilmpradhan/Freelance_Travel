<?php

namespace App\Services;

class ResponseService
{
    public function sendError($title, $data = [], $code = 400)
    {
        $response = [
            'success' => false,
            'title' => $title,
            'code' => $code,
            'data' => $data
        ];

        return response()->json($response, $code);
    }


    public function sendResponse($title, $data = [], $code = 200)
    {
        $response = [
            'success' => true,
            'title' => $title,
            'code' => $code,
            'data' => $data
        ];

        return response()->json($response, 200);
    }
}
