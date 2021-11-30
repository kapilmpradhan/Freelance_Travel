<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use HasFactory;

    protected $table = 'quotes';
    protected $fillable = ['email', 'json'];


    public function CallApi($token)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "".env('API_URL')."/bookingreference");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$token."",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $result = curl_exec($curl);
        return $result;
    }

    public function rule()
    {
        return [
            'email' => 'required|email|max:100',
        ];
    }

    public function storeQuote($request)
    {
        $params = [
            'email' => $request->email,
            'json' => json_encode($request->json)
        ];
        $account = $this->where('email', $request->email)->first();
        if ( $account ) {
            $account->update($params);
            return $this->where('email', $request->email)->first();
        }
        return $this->create($params);
    }

    public function getDetailQuote($email)
    {
        return $this->where('email', $email)->first();
    }

    
}
