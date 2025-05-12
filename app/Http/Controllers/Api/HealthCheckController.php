<?php

namespace App\Http\Controllers\Api;

use Google\Rpc\Context\AttributeContext\Request;

class HealthCheckController extends BaseController
{
    public function healthCheck(Request $request)
    {
        return $this->sendResponse('Healthy');
    }
}
