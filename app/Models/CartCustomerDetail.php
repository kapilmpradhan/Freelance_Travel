<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Validator;

class CartCustomerDetail extends Model
{
    use HasFactory;

    protected $table = 'cart_customer_details';
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'email',
        'postal_code',
        'customer_index',
        'country_code',
        'phone_number'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function dateOfBirth(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d-M-Y'),
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d')
        );
    }

    /**
     * Validator for CartCustomerDetails fields
     *
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public static function validator(array $data)
    {
        return Validator::make(
            ['items' => $data], // Wrap data in a parent key
            [
            'items' => 'array',
            'items.*.firstName' => 'required|string|max:255',
            'items.*.lastName' => 'required|string|max:255',
            'items.*.dateOfBirth' => 'required|date_format:d-M-Y',
            'items.*.email' => 'required|email|max:255',
            'items.*.phoneNumber' => 'required|string',
            'items.*.postalCode' => 'required|string|max:20',
            'items.*.customerIndex' => 'required|integer|min:0',
            ]
        );
    }
}
