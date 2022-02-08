<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BaseResource;
use App\Jobs\sendMail;
use App\Models\Order;
use App\Models\Quote;
use Illuminate\Http\Request;
use Validator;

class QuotetController extends BaseController
{
    public function saveQuote(Request $request, Quote $quote)
    {
        $validate = Validator::make($request->all(), $quote->rule($request));
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors(), 422);
        }
        $create = $quote->storeQuote($request);
        return $this->sendResponse(new BaseResource($create), __('successfully'), 200);
    }

    public function detailQuote($email, Quote $quote)
    {
        $quote = $quote->getDetailQuote($email);

        if ($quote) {
            return $this->sendResponse(new BaseResource($quote), __('successfully'), 200);
        }
        return $this->sendResponse(null, __('fail'), 200);
    }

    public function resendVoucher($orderId, Request $request)
    {
        $token = $request->token_access;
        $url = $request->request_url;
        if ($request->hasHeader('fttoken')) {
            $token = $request->header('fttoken');
        }
        if ($request->hasHeader('fturl')) {
            $url = $request->header('fturl');
        }
        $orderDetail = json_decode(getBookingDetail($url, $orderId, $token));
        if (isset($orderDetail->bookingReference)) {
            $email = @$orderDetail->products[0]->redeemers[0]->email ?: "james.nguyen@adamosoft.com";
            // $agentEmail = "james.nguyen@adamosoft.com";
            dispatch(new sendMail($email, $orderDetail));
        }
    }
}
