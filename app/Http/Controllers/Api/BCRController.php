<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingCancelRequest;
use Illuminate\Http\Request;

class BCRController extends Controller
{
    public function get($email, BookingCancelRequest $bookingCancelRequest)
    {
        return $bookingCancelRequest->getAllByEmail($email);
    }

    public function getAll(BookingCancelRequest $bookingCancelRequest)
    {
        return $bookingCancelRequest->get();
    }

    public function getDetail($bookingReference, BookingCancelRequest $bookingCancelRequest)
    {
        return $bookingCancelRequest->getAllByBookingReference($bookingReference);
    }

    public function cancelRequest(Request $request, BookingCancelRequest $bookingCancelRequest)
    {
        $data = [
            "email" => $request->email,
            "bookingReference" => $request->bookingReference,
            "voucherNumber" => $request->voucherNumber,
            "quantity" => $request->quantity,
            "reason" => $request->reason,
        ];

        return $bookingCancelRequest->createCancelRequest($data);
    }

    public function makeCancelRequest(Request $request, BookingCancelRequest $bookingCancelRequest)
    {
        $data = [
            "bookingReference" => $request->bookingReference,
            "voucherNumber" => $request->voucherNumber,
            "quantity" => $request->quantity,
            "reason" => $request->reason,
        ];

        return $bookingCancelRequest->createCancelRequest($data);
    }
}
