<?php

namespace App\Http\Resources;

use App\Models\AgentToken;

class UserResource
{
    public function userDetail($data)
    {
        $has_agent_token = AgentToken::where('user_id', $data->uuid)->exists();

        return [
            'id' => $data->uuid,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'sso_type' => $data->sso_type,
            'profile_status' => $data->profile_status,
            'is_agent_integrated' => $has_agent_token,
            'is_email_verified' => $data->is_email_verified,
            'created_at' => $data->created_at,
        ];
    }
}
