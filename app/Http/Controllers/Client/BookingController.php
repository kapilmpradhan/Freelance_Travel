<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class BookingController extends Controller
{
    public function paymentSuccess($slug, Request $request)
    {
        $bookingReference = substr($request->cmsgw_OrderInfo, 4);
        $sessions = Session::put("order_" . $bookingReference . "", [[
            'slug' => $slug,
            'bookingReference' => $bookingReference,
            'message' => $request->cmsgw_Message,
            'status' => $request->status,
            'param' => $_SERVER['QUERY_STRING'],
        ]]);
        return redirect()->route('payment.detail', $bookingReference);
    }

    public function paymentDetail($bookingReference, Order $order)
    {
        $session = Session::get("order_" . $bookingReference . "");
        Session::forget("order_" . $bookingReference . "");
        $bookingOrder = $order->getAccessToken($session[0]['bookingReference']);
        dd($bookingOrder);
        return view('payment', ['order' => $session[0], 'bookingOrder' => $bookingOrder]);
    }

    public function bookingDetail($bookingReference, Order $order)
    {
        $booking = $order->getAccessToken($bookingReference);
        if ($booking) {
            $result = $order->CallApiDetailBooking($booking);
            // dd(json_decode($result));
            return view('email.email', ['data' => json_decode($result)]);
        }
        return redirect(env("APP_BASE_URL"));
    }
}
