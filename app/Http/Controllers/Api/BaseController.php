<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendAgent;
use App\Jobs\SendMail;
use App\Mail\SendShareToAgent;
use App\Mail\SendShareToEmail;
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

    public function sendError($error, $errorMessages = [], $code = 400)
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

        $emailShare = new SendShareToEmail($request);

        Mail::to($request->email)->send($emailShare);

        $agentEmail = @$request->agent->email ?? "support@freelancetravel.com";

        $agentShare = new SendShareToAgent();
        Mail::to($agentEmail)->send($agentShare);

        $response = [
            'success' => true,
            'data' => $request->all(),
            'code' => 200
        ];

        return response()->json($response, 200);
    }

    public function sendMail(Request $request)
    {

        $agentEmail = @$request->agent->email ?? "support@freelancetravel.com";

        //  dispatch(new SendMail( $agentEmail, $orderDetail ));

        $url = env('PRODUCTION_URL');
        $accessToken = getToken();
        $accessToken = json_decode($accessToken);
        $token = @$accessToken->access_token;
        $token = 'N2E5NTg5MTQyMmYwMDI4YTQ3NDI2YmFiNDI0NjMyYmE4NzhiOWRjOGY2ZTkxYTFhYTk5NDBhMDQ3ZWMxYWZjOA';
        $orderId = '3521834';
        $orderDetail = json_decode(getBookingDetail($url, $orderId, $token));
        // dd($orderDetail, $token);

        if (isset($orderDetail->bookingReference)) {
            // $email = @$orderDetail->products[0]->redeemers[0]->email ?: "support@freelancetravel.com";
            $email = $request->email;
            // $agentEmail = "support@freelancetravel.com";
            dispatch(new SendMail($email, $orderDetail));
            dispatch(new SendAgent($email, $orderDetail));
        }
        $emailShare = new SendShareToEmail($request);
        Mail::to($request->email)->send($emailShare);

        $agentShare = new SendShareToAgent();
        Mail::to($request->email)->send($agentShare);

        $response = [
            'success' => true,
            'data' => [
                'order' => $orderDetail,
                'email' => $request->email
            ],
            'code' => 200
        ];
        return response()->json($response, 200);
    }
}
