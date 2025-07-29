<?php

namespace App\Http\Resources;

use App\Models\UserAgent;

class UserResource
{
    public function userDetail($data)
    {
        $userAgent = new UserAgent();
        $activeAgent = $userAgent->getActiveAgent($data->uuid);
        $hasUserAgent = (bool)$activeAgent;

        $returnData =  [
            'id' => $data->uuid,
            'title' => $data->title,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'nickname' => $data->nickname,
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
            'is_temporarily_deleted' => $data->is_temporarily_deleted,
            'is_points_displayed' => (bool) $data->is_points_displayed,
        ];

        if ($data->sso_type === 'apple') {
            $returnData['verified_email'] = $data['verified_email'];
        }

        return $returnData;
    }
}
