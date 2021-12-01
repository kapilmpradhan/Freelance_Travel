<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BaseResource;
use App\Models\Quote;
use Illuminate\Http\Request;
use Validator;

class QuotetController extends BaseController
{
    public function saveQuote(Request $request, Quote $quote)
    {
        $header = $request->header('token_access');

        $checkAuth = json_decode($quote->CallApi($request->request_url, $header), true);

        if (!@$checkAuth['bookingReference']) {
            return response()->json([
                'message' => __('auth.unauthenticated')
            ], 401);
        }
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
}
