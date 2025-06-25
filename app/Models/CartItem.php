<?php

namespace App\Models;

use Carbon\Carbon;
use App\DTOs\ItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;

    protected $table = 'cart_items';
    protected $fillable = [
        'user_id',
        'tdms_product_id',
        'group_id',
        'product_version',
        'product_price_details_id',
        'time_id',
        'commences',
        'booking_date',
        'start_date',
        'days',
        'selected_index',
        'availability',
        'availability_last_updated_at',
        'booking_details',
        'booking_quantity',
        'booking_data',
        'user_order_id',
        'quote_id',
        'is_direct_purchase',
    ];
    protected $casts = [
        'availability' => 'array',
        'booking_details' => 'array',
        'booking_data' => 'array',
    ];

    /**
     * Relationship: Each cartItem belongs to a single user.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'tdms_product_id', 'tdms_product_id');
    }

    public function productPriceAvailability()
    {
        return $this->belongsTo(
            ProductPriceAvailability::class,
            'product_price_details_id', // Foreign key in cart_items
            'product_price_details_id'  // Referenced key in product_price_availabilities
        )->whereColumn(
            'cart_items.tdms_product_id', // Ensure tdms_product_id also matches
            'product_price_availabilities.tdms_product_id'
        );
    }

    protected function bookingDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d-M-Y'),
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d')
        );
    }

    protected function startDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d-M-Y'),
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d')
        );
    }

    public function addProductsToCartRule()
    {
        return [
            'tdms_product_id' => 'required|int',
            'product_price_details_id' => 'required|int',
            'booking_date' => 'required|date_format:j-M-Y',
            'time_id' => 'required|string'
        ];
    }

    protected static array $baseSaveItemsRule = [
        'tdmsProductId' => 'required|integer',
        'productPricesDetailsId' => 'required|integer',
        'timeId' => 'string',
        'commences' => 'string|nullable',
        'bookingData' => 'array',
        'bookingData.timeId' => 'string',
        'bookingData.commences' => 'string',
        'bookingData.datePriceCacheId' => 'string',
        'bookingData.pickupId' => 'string',
        'bookingData.pickupLocation' => 'string',
        'bookingData.dropoffId' => 'string',
        'bookingData.dropoffLocation' => 'string',

        'bookingData.bookingComment' => 'string',
        // Must be in format 30-Nov-2012
        'startDate' => 'required|date|date_format:d-M-Y',
        // Must be greater than zero
        'days' => 'required|integer|min:1',
         // Must be an array with at least one element
        'selectedAvailableIndices' => 'required|array|min:1',
        // Each element in the array must be an integer greater than or equal to 0
        'selectedAvailableIndices.*' => 'integer|min:0',
    ];

    public static function saveItemsRule()
    {
        return self::$baseSaveItemsRule;
    }

    public static function saveItemsV2Rule()
    {
        return [
            "tdmsProductId" => 'required|integer',
            "productPricesDetails" => 'required|array|min:1',
            "productPricesDetails.*.quantityDetails" => 'required|array|min:1',
            "productPricesDetails.*.quantityDetails.*.quantity" => 'required|integer|min:1',
            "productPricesDetails.*.quantityDetails.*.bookingDate" => 'required|date_format:d-M-Y',
            "productPricesDetails.*.quantityDetails.*.timeId" => 'required|string',
            "productPricesDetails.*.quantityDetails.*.commences" => 'nullable|string',
            "productPricesDetails.*.quantityDetails.*.bookingData" => 'nullable|array',
            'startDate' => 'required|date|date_format:d-M-Y',
            'days' => 'required|integer|min:1',
            'selectedAvailableIndices' => 'required|array|min:1',
            'selectedAvailableIndices.*' => 'integer|min:0',
        ];
    }

    public static function saveItemsInNewQuote()
    {
        return array_merge(self::$baseSaveItemsRule, [
            'quoteTitle' => 'required|string',
        ]);
    }

    public static function saveItemsInNewQuoteV2()
    {
        return array_merge(self::saveItemsV2Rule(), [
            'quoteTitle' => 'required|string',
        ]);
    }

    public static function updateItemBookingDataRule()
    {
        return [
            'quantity' => 'required|integer',
            'bookingData' => 'array',
            'bookingData.optionalData' => 'array',
            'bookingData.pickupId' => 'string',
            'bookingData.pickupLocation' => 'string',
            'bookingData.dropoffId' => 'string',
            'bookingData.dropoffLocation' => 'string'
        ];
    }

    public static function updateItemBookingDataV2Rule()
    {
        return [
            '*.cartItemId' => 'required|integer',
            '*.quantity' => 'required|integer',
            '*.bookingData' => 'required|array',
            '*.bookingData.*.quantityIndex' => 'required|integer',
            '*.bookingData.*.timeId' => 'required|string',
            '*.bookingData.*.commences' => 'nullable|string',
            '*.bookingData.*.pickupId' => 'nullable|string',
            '*.bookingData.*.pickupLocation' => 'nullable|string',
            '*.bookingData.*.dropoffId' => 'nullable|string',
            '*.bookingData.*.dropoffLocation' => 'nullable|string',
            '*.bookingData.*.redeemers' => 'array',
            '*.bookingData.*.redeemers.*' => 'integer|min:1',
            '*.bookingData.*.optionalData' => 'nullable|array',
        ];
    }

    public static function directPurchaseRule()
    {
        $rule = self::$baseSaveItemsRule;
        $rule['bookingData.optionalData'] = 'array';
        $rule['redeemers'] = 'array';
        $rule['redeemers.*.title'] = 'in:Mr,Mrs';
        $rule['redeemers.*.firstName'] = 'required|string|max:200|regex:' . config('vars.only_char_regex');
        $rule['redeemers.*.lastName'] = 'required|string|max:200|regex:' . config('vars.only_char_regex');
        $rule['redeemers.*.dateOfBirth'] = 'required|date_format:d-M-Y';
        $rule['redeemers.*.email'] = 'required|email|max:255';
        $rule['redeemers.*.phoneNumber'] = 'required|string';
        $rule['redeemers.*.postalCode'] = 'required|string|max:20';
        $rule['redeemers.*.countryCode'] = 'required|string';
        $rule['redeemers.*.customerIndex'] = 'required|integer|min:0';

        return $rule;
    }

    public static function directPurchaseRuleV2()
    {
        $rule = self::saveItemsV2Rule();
        unset(
            $rule['productPricesDetails.*.quantityDetails.*.timeId'],
            $rule['productPricesDetails.*.quantityDetails.*.commences']
        );
        $rule['productPricesDetails.*.quantityDetails.*.bookingData'] = 'required|array';
        $rule['productPricesDetails.*.quantityDetails.*.bookingData.*.quantityIndex'] = 'required|integer';
        $rule['productPricesDetails.*.quantityDetails.*.bookingData.*.timeId'] = 'required|string';
        $rule['productPricesDetails.*.quantityDetails.*.bookingData.*.commences'] = 'nullable|string';
        $rule['productPricesDetails.*.quantityDetails.*.bookingData.*.pickupId'] = 'nullable|string';
        $rule['productPricesDetails.*.quantityDetails.*.bookingData.*.pickupLocation'] = 'nullable|string';
        $rule['productPricesDetails.*.quantityDetails.*.bookingData.*.dropoffId'] = 'nullable|string';
        $rule['productPricesDetails.*.quantityDetails.*.bookingData.*.dropoffLocation'] = 'nullable|string';
        $rule['productPricesDetails.*.quantityDetails.*.bookingData.*.redeemers'] = 'array|min:1';
        $rule['productPricesDetails.*.quantityDetails.*.bookingData.*.optionalData'] = 'nullable|array';

        return $rule;
    }

    public function storeCartItem($user, $data)
    {
        return $this->create($data);
    }

    public static function userItems($userId, ItemType $itemType)
    {
        return CartItem::where('user_id', $userId)
            ->where('is_direct_purchase', $itemType->isDirect)
            ->when($itemType->isQuote, fn ($query) => $query->where('quote_id', $itemType->typeId))
            ->when($itemType->isCart, fn ($query) => $query->whereNull('quote_id'))
            ->when($itemType->isCartItem, fn ($query) => $query->where('id', $itemType->typeId))
            ->when($itemType->isGroup, fn ($query) => $query->where('group_id', $itemType->typeId))
            ->when($itemType->isProduct, function ($query) use ($itemType) {
                if (is_null($itemType->subTypeId)) {
                    $query->where('tdms_product_id', $itemType->typeId);
                } else {
                    $query->where('quote_id', $itemType->typeId)
                          ->where('tdms_product_id', $itemType->subTypeId);
                }
            })
            ->when(
                $itemType->isQuoteItem,
                fn ($query) => $query->where('quote_id', $itemType->typeId)
                    ->where('id', $itemType->subTypeId)
            )
            ->whereNull('user_order_id')
            ->get();
    }

    public static function userCartItems($userId)
    {
        return self::userItems($userId, ItemType::cart());
    }

    public static function userCartItemsByProduct($userId, string $productId)
    {
        return self::userItems($userId, ItemType::product($productId));
    }

    public static function userCartItem($userId, string $cartItemId)
    {
        return self::userItems($userId, ItemType::cartItem($cartItemId));
    }

    public static function userGroupItems($userId, ItemType $type)
    {
        return self::userItems($userId, $type);
    }

    public static function userQuoteItems(string $userId, ItemType $type)
    {
        return self::userItems($userId, $type);
    }

    public static function userQuoteItemsByProduct($userId, ItemType $type)
    {
        return self::userItems($userId, $type);
    }

    public static function userQuoteItem(string $userId, ItemType $type)
    {
        return self::userItems($userId, $type);
    }

    public static function userDirectPurchaseItems(string $userId)
    {
        return self::userItems($userId, ItemType::direct());
    }
}
