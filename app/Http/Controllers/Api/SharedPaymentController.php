<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemType;
use App\Http\Controllers\Api\BaseController;
use App\Jobs\SharePaymentLinkJob;
use App\Models\CartCustomerDetail;
use App\Models\Quote;
use App\Models\SharedPayment;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharedPaymentController extends BaseController
{
    public function sharePaymentLink(Request $request)
    {
        $quoteId = $request->query('quoteId') ?? null;
        $isNew = $request->query('isNew') ?? null;
        $requestData = $request->all();

        if (!$quoteId) {
            return $this->sendError('quoteId is required');
        }

        $quote = Quote::where('id', $quoteId)
            ->where('user_id', auth()->user()->uuid)
            ->where('is_paid', false)
            ->first();

        if (!$quote) {
            return $this->sendError('Quote not found');
        }

        if ($isNew) {
            $validator = Validator::make($requestData, [
                'name' => 'string|required',
                'email' => 'required|email'
            ]);
            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors());
            }

            $name = $requestData['name'];
            $email = $requestData['email'];
            $redeemerId = null;
        } else {
            $validator = Validator::make($requestData, [
                'redeemerId' => 'integer|required'
            ]);
            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors());
            }

            $redeemer = CartCustomerDetail::where('id', $requestData['redeemerId'])
                ->where('user_id', auth()->user()->uuid)
                ->first();
            if (!$redeemer) {
                return $this->sendError('Redeemer not found');
            }

            $name = "{$redeemer->first_name} {$redeemer->last_name}";
            $email = $redeemer->email;
            $redeemerId = $requestData['redeemerId'];
        }

        $itemType = ItemType::quote($quoteId);
        $postOrderResponse = BookingService::postOrder(
            userId: $request->user->uuid,
            intent: 'pay-now',
            pointsApplied: null,
            processAsQuote: true,
            itemType: $itemType
        );

        if ($postOrderResponse->isError()) {
            return $this->sendResponseFromService($postOrderResponse);
        }

        $paymentLink = $postOrderResponse->data['payNow']['redirectUrl'];

        $lastSharedPayment = SharedPayment::where('quote_id', $quoteId)
            ->where('is_latest', true)
            ->first();
        if ($lastSharedPayment) {
            $lastSharedPayment->is_latest = false;
            $lastSharedPayment->save();
        }

        $sharedPayment = SharedPayment::updateOrCreate(
            [
                'quote_id' => $quoteId,
                'email' => $email,
                'redeemer_id' => $redeemerId,
            ],
            [
                'name' => $name,
                'is_latest' => true,
                'payment_link' => $paymentLink
            ]
        );

        SharePaymentLinkJob::dispatch($sharedPayment, app('platform'));

        return $this->sendResponse('Payment link sent');
    }

    public function redirectToStripePayment(Request $request, $quoteId)
    {
        $sharedPayment = SharedPayment::where('quote_id', $quoteId)
            ->where('is_latest', true)
            ->first();

        return redirect()->away($sharedPayment->payment_link);
    }
}
