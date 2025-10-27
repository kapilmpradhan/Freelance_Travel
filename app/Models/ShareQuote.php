<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function Laravel\Prompts\table;

class ShareQuote extends Model
{
    use HasFactory;

    protected $table = 'share_quotes';
    protected $fillable = [
        'shared_by_user_id',
        'shared_to_email',
        'quote_id',
        'is_accepted',
        'is_declined',
        'is_quote_deleted',
        'timestamp',
    ];
}
