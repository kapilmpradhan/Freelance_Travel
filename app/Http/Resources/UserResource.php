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

        $returnData =  [
            'id' => $data->uuid,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'date_of_birth' => $data->date_of_birth,
            'phone_number' => $data->phone_number,
            'post_code' => $data->post_code,
            'country_code' => $data->country_code,
            'sso_type' => $data->sso_type,
            'profile_status' => $data->profile_status,
            'is_agent_integrated' => $hasUserAgent,
            'is_email_verified' => $data->is_email_verified,
            'created_at' => $data->created_at,
        ];

        if ($data->sso_type === 'apple') {
            $returnData['verified_email'] = $data['verified_email'];
        }

        return $returnData;
    }
}
