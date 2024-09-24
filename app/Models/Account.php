<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounts';
    protected $fillable = ['email', 'json'];

    public function rule()
    {
        return [
            'email' => 'required|email|max:100',
        ];
    }

    public function storeAccount($request)
    {
        $params = [
            'email' => $request->email,
            'json' => json_encode($request->json)
        ];
        $account = $this->where('email', $request->email)->first();
        if ($account) {
            $account->update($params);
            return $this->where('email', $request->email)->first();
        }
        return $this->create($params);
    }

    public function getDetailAccount($email)
    {
        return $this->where('email', $email)->first();
    }
}
