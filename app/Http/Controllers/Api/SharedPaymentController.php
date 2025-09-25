<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemType;
use App\Http\Controllers\Api\BaseController;
use App\Jobs\SharePaymentLinkJob;
use App\Models\CartCustomerDetail;
use App\Models\Quote;
use App\Models\SharedPayment;
use App\Services\BookingService;
use App\Services\ServiceResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharedPaymentController extends BaseController
{
    public function sharePaymentLink(Request $request, $quoteId)
    {
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

    public function addPaymentLinkReceiver(Request $request, $quoteId)
    {
        $requestData = $request->all();
        $validator = Validator::make($requestData, [
            'name' => 'string|required',
            'email' => [
                'required',
                'email',
                'unique:shared_payments,email,NULL,id,quote_id,' . $quoteId,
            ],
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors());
        }

        $quote = Quote::where('id', $quoteId)
            ->where('user_id', auth()->user()->uuid)
            ->where('is_paid', false)
            ->first();

        if (!$quote) {
            return $this->sendError('Quote not found');
        }

        $name = $requestData['name'];
        $email = $requestData['email'];

        $sharedPayment = SharedPayment::create([
            'name' => $name,
            'email' => $email,
            'quote_id' => $quoteId,
            'is_latest' => true
        ]);

        return $this->sendResponse('Payment link receiver added', $sharedPayment);
    }

    public function redirectToStripePayment(Request $request, $quoteId)
    {
        $sharedPayment = SharedPayment::where('quote_id', $quoteId)
            ->where('is_latest', true)
            ->first();

        return redirect()->away($sharedPayment->payment_link);
    }

    public function listPaymentReceivers(Request $request, $quoteId)
    {
        $redeemers = CartCustomerDetail::where('quote_id', $quoteId)
            ->orWhere(function ($query) {
                $query->where('is_primary', true)
                      ->where('user_id', auth()->user()->uuid);
            })
            ->select('id', 'first_name', 'last_name')
            ->get();

        $receivers = [];
        foreach ($redeemers as $redeemer) {
            $receivers[] = [
                'name' => "{$redeemer->first_name} {$redeemer->last_name}",
                'email' => $redeemer->email,
                'redeemer_id' => $redeemer->id
            ];
        }

        $sharedPayments = SharedPayment::where('quote_id', $quoteId)
            ->whereNull('redeemer_id')
            ->get();
        foreach ($sharedPayments as $shared) {
            $receivers[] = [
                'name' => $shared->name,
                'email' => $shared->email,
                'redeemer_id' => $shared->redeemer_id
            ];
        }


        return $this->sendResponse('Payment receivers list', $receivers);
    }

    public function sharedPaymentSentList(Request $request, $quoteId)
    {
        $sharedPayment = SharedPayment::where('quote_id', $quoteId)
            ->select('id', 'name', 'email', 'redeemer_id')
            ->get();

        return $this->sendResponse('Shared payment list', $sharedPayment);
    }

    public function resendPaymentLink(Request $request, $quoteId, $sharePaymentId)
    {
        $quote = Quote::where('user_id', auth()->user()->uuid)
            ->where('id', $quoteId)
            ->where('is_paid', false)
            ->first();

        if (!$quote) {
            return $this->sendError('Quote not found');
        }

        $lastSharedPayment = SharedPayment::where('quote_id', $quoteId)
            ->where('is_latest', true)
            ->first();
        if ($lastSharedPayment) {
            $lastSharedPayment->is_latest = false;
            $lastSharedPayment->save();
        }

        $sharePayment = SharedPayment::where('id', $sharePaymentId)
            ->first();
        $sharePayment->is_latest = true;
        $sharePayment->payment_link = $lastSharedPayment->payment_link;
        $sharePayment->save();

        SharePaymentLinkJob::dispatch($sharePayment, app('platform'));

        return $this->sendResponse('Payment link re-sent');
    }
}
