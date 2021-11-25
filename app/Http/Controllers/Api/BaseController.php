<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    public function connectPayment(Request $request, Order $order)
    {
        $create = $order->storeOrder($request);
        if ( $create ) {
            return response()->json([
                'message'=>__('successfully'),
                'data' => $create,
            ], 200);
        }
        return response()->json([
            'message'=>__('fail'),
            'data' => "",
        ], 400);
    }

    public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
            'code' => $code
        ];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
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
