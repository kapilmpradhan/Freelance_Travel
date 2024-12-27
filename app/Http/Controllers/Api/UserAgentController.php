<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\UserAgent;
use Illuminate\Http\Request;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Validator;

class UserAgentController extends BaseController
{
    public function addUserAgent(Request $request)
    {
        $data = $request->all();
        $data['user_id'] = $request->user->uuid;
        $validate = Validator::make($data, ['username' => 'required|email', 'password' => 'required|string']);
        if ($validate->fails()) {
            return $this->sendError('Error occured', $validate->errors(), 400);
        }
        $userAgentExists = UserAgent::where('email', $data['username'])->first();
        if ($userAgentExists) {
            return $this->sendError('Agent with that username already exists');
        }

        try {
            $userAgentData = UserAgentService::retrieveAgentData(
                $data['username'],
                $data['password']
            );

            if (!$userAgentData) {
                return $this->sendError('Agent not found');
            }

            $userAgentData["user_id"] = $request->user->uuid;
            $userAgent = UserAgent::create($userAgentData);

            return $this->sendResponse('Agent added', $userAgent->toArray(), 201);
        } catch (Exception $e) {
            return $this->sendError('Error occured', [
                "errorCode" => "100005",
                "errorMessage" => $e->getMessage()
            ]);
        }
    }

    public function getUserAgents(Request $request)
    {
        $userAgents = UserAgent::where('user_id', $request->user->uuid)->get();
        return $this->sendResponse('User agents', $userAgents->toArray());
    }

    public function deleteUserAgent(Request $request, $userAgentId)
    {
        if (!$userAgentId) {
            return $this->sendError('User agent ID required');
        }
        $userAgent = UserAgent::where('id', $userAgentId)
                            ->where('user_id', $request->user->uuid)
                            ->first();
        if (!$userAgent) {
            return $this->sendError('User agent does not exist');
        }

        $userAgent->delete();
        return $this->sendResponse('User agent deleted');
    }
}
