<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GoogleService
{
    public function googleUserDetail($accessToken)
    {
        $response = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
        if ($response->successful()) {
            return $response->json();
        }
        return false;
    }
}
