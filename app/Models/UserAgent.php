<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAgent extends Model
{
    use HasFactory;

    protected $table = 'user_agents';
    protected $fillable = [
        'user_id',
        'email',
        'branch_code',
        'is_profile',
        'is_default',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
}
