<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class BookingController extends Controller
{
    public function paymentSuccess($lug, Request $request)
    {
        $bookingReference = substr($request->cmsgw_OrderInfo,4);
        $sessions = Session::put("order_".$bookingReference."", [[
            'slug' => $lug,
            'bookingReference' => $bookingReference,
            'message' => $request->cmsgw_Message,
            'status' => $request->status,
            'param' => $_SERVER['QUERY_STRING'],
        ]]);
        return redirect()->route('payment.detail',$bookingReference);
    }

    public function paymentDetail($bookingReference, Order $order)
    {
        $session = Session::get("order_".$bookingReference."");
        Session::forget("order_".$bookingReference."");
        $token = $order->getAccessToken($session[0]['bookingReference']);
        return view('payment',['order' => $session[0], 'token'=> $token[0]]);
    }
}
