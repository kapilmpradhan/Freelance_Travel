<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirebaseFcmToken extends Model
{
    use HasFactory;

    protected $table = 'firebase_fcm_tokens';
    protected $fillable = ['user_id', 'token', 'client_user_agent'];
}
