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
        'title',
        'first_name',
        'last_name',
        'date_of_birth',
        'email',
        'postal_code',
        'customer_index',
        'country_code',
        'phone_number',
        'quote_id',
        'user_order_id',
        'is_direct_purchase',
        'is_deleted',
        'is_primary'
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
            'items.*.title' => 'in:Master,Mr,Miss,Mrs,Ms,Mx',
            'items.*.firstName' => [
                'required',
                'string',
                'max:200',
                'regex:' . config('vars.only_char_regex')
            ],
            'items.*.lastName' => [
                'required',
                'string',
                'max:200',
                'regex:' . config('vars.only_char_regex')
            ],
            'items.*.dateOfBirth' => 'required|date_format:d-M-Y',
            'items.*.email' => 'required|email|max:255',
            'items.*.phoneNumber' => 'required|string',
            'items.*.postalCode' => 'required|string|max:20',
            'items.*.countryCode' => 'required|string',
            'items.*.customerIndex' => 'required|integer|min:0',
            ]
        );
    }

    public static function addNewRedeemerRule()
    {
        $onlyCharRegex = config('vars.only_char_regex');

        return [
            "title" => "required|in:Master,Mr,Miss,Mrs,Ms,Mx",
            "firstName" => "required|string|max:200|regex:{$onlyCharRegex}",
            "lastName" => "required|string|max:200|regex:{$onlyCharRegex}",
            "dateOfBirth" => "required|date_format:d-M-Y",
            "email" => "required|email|max:255",
            "phoneNumber" => "required|string",
            "postalCode" => "required|string|max:20",
            "countryCode" => "required|string"
        ];
    }
}
