<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleService
{
    public function googleTokenDetail($googleToken)
    {
        $response = Http::withToken($googleToken)->get('https://oauth2.googleapis.com/tokeninfo');
        if ($response->successful()) {
            return $response->json();
        }
        return false;
    }

    public function googleUserDetail($accessToken)
    {
        $response = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
        if ($response->successful()) {
            return $response->json();
        }
        return false;
    }
}
