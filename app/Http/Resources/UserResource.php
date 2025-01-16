<?php

namespace App\Http\Resources;

use App\Models\UserAgent;

class UserResource
{
    public function userDetail($data)
    {
        $userAgent = new UserAgent();
        $activeAgent = $userAgent->getActiveAgent($data->uuid);
        $hasUserAgent = $activeAgent ? true : false;

        return [
            'id' => $data->uuid,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'sso_type' => $data->sso_type,
            'profile_status' => $data->profile_status,
            'is_agent_integrated' => $hasUserAgent,
            'is_email_verified' => $data->is_email_verified,
            'created_at' => $data->created_at,
        ];
    }
}
