<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $table = 'otps';
    protected $fillable = ['user_id', 'otp', 'created_timestamp', 'expire_timstamp', 'is_verified'];

    protected static function boot()
    {
        parent::boot();

        // Automatically set timestamps on creation
        static::creating(function ($otp) {
            $otp->created_timestamp = Carbon::now();
            $otp->expire_timestamp = Carbon::now()->addMinutes(5);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function storeOtp($data)
    {
        return $this->create($data);
    }

    public function getOtp($user, $otp)
    {
        return $this->where('user_id', $user->uuid)
                    ->where('otp', $otp);
    }
}
