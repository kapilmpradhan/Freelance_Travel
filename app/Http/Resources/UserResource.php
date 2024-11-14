<?php

namespace App\Http\Resources;

class UserResource
{
    public function userDetail($data)
    {
        return [
            'id' => $data->uuid,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'sso_type' => $data->sso_type,
            'created_at' => $data->created_at,
        ];
    }
}
