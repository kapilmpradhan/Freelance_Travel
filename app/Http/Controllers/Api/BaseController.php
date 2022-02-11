<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\sendShareToAgent;
use App\Mail\sendShareToEmail;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BaseController extends Controller
{
    public function connectPayment(Request $request, Order $order)
    {
        $create = $order->storeOrder($request);
        if ($create) {
            return response()->json([
                'message' => __('successfully'),
                'data' => $create,
            ], 200);
        }
        return response()->json([
            'message' => __('fail'),
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

    public function shareBooking(Request $request)
    {

        // return view("email.emailShare", ["data" => $request]);

        $emailShare = new sendShareToEmail($request);

        Mail::to($request->email)->send($emailShare);

        $agentEmail = @$request->agent->email ?? "james.nguyen@adamosoft.com";

        $agentShare = new sendShareToAgent();
        Mail::to($agentEmail)->send($agentShare);

        $response = [
            'success' => true,
            'data' => $request->all(),
            'code' => 200
        ];

        return response()->json($response, 200);
    }
}
