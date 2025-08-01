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
        'is_test',
        'is_deleted',
        'platform',
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

    public static function updateDiscountRule()
    {
        return [
            'title' => 'string|max:255',
            'description' => 'string|max:255',
            'percentage' => 'integer|min:1|max:100',
            'is_active' => 'boolean',
            'activate_at' => 'date',
            'expires_at' => 'date|after:activate_at',
        ];
    }

    public static function getNonDeletedDiscounts()
    {
        return self::where('is_deleted', false)
            ->where('platform', app('platform'))->get();
    }

    public static function getActiveDiscount()
    {
        return self::getNonDeletedDiscounts()
                    ->where('is_active', true)
                    ->first();
    }

    public static function getDiscountById($id)
    {
        return self::getNonDeletedDiscounts()
                    ->where('id', $id)
                    ->first();
    }
}
