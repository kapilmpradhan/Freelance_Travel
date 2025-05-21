<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory;

    protected $table = 'discounts';
    protected $fillable = [
        'title',
        'description',
        'percentage',
        'activate_at',
        'expires_at',
        'is_active',
        'is_deleted',
    ];

    public static function addDiscountRule()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'percentage' => 'required|integer|min:1|max:100',
            'activate_at' => 'required|date',
            'expires_at' => 'required|date|after:activate_at',
        ];
    }
}
