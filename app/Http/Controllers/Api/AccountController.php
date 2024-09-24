<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BaseResource;
use App\Models\Account;
use Illuminate\Http\Request;
use Validator;

class AccountController extends BaseController
{
    public function saveAccount(Request $request, Account $account)
    {
        $validate = Validator::make($request->all(), $account->rule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors(), 422);
        }
        $create = $account->storeAccount($request);
        return $this->sendResponse(new BaseResource($create), __('successfully'), 200);
    }

    public function detailAccount($email, Account $account)
    {
        $account = $account->getDetailAccount($email);
        if ($account) {
            return $this->sendResponse(new BaseResource($account), __('successfully'), 200);
        }
        return $this->sendResponse(null, __('fail'), 200);
    }
}
