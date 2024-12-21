<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileToken extends Model
{
    use HasFactory;

    protected $table = 'profile_tokens';
    protected $fillable = ['agent_email', 'branch_code', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
}
