<?php

namespace App\Http\Controllers\Api;

use App\Services\ServiceException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class AppMetaDataController extends BaseController
{
    public function setMobileMinSupportedVersion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'version' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                title: 'Validation Error',
                data: $validator->errors(),
                code: 400
            );
        }

        try {
            $platform = app('platform');
            $version = $request->input('version');

            Redis::set($platform . 'mobile_min_supported_version:', $version);
            return $this->sendResponse(
                data: [
                    'platform' => $platform,
                    'version' => $version
                ],
                title: 'Mobile min supported version updated successfully'
            );
        } catch (ServiceException $e) {
            return $this->sendResponseFromService(
                $e->toServiceResponse()
            );
        }
    }

    public function getMobileMinSupportedVersion(Request $request)
    {
        $platform = app('platform');

        try {
            $version = Redis::get($platform . 'mobile_min_supported_version:');
            if (!$version) {
                $version = '0.0.0';
            }

            return $this->sendResponse(
                data: [
                    'platform' => $platform,
                    'version' => $version
                ],
                title: 'Mobile min supported version'
            );
        } catch (ServiceException $e) {
            return $this->sendResponseFromService(
                $e->toServiceResponse()
            );
        }
    }
}
