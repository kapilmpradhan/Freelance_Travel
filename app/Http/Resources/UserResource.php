<?php

namespace App\Http\Resources;

class UserResource
{
    public function userDetail($data)
    {
        return [
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'created_at' => $data->created_at,
        ];
    }
}
