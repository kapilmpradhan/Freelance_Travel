<?php

namespace App\DTOs;

class RedeemerProductsOrderData
{
    public $cartItemId;
    public $productPricesDetailsId;
    public $redeemerQuantity;
    public array $bookings;

    public function __construct(
        $cartItemId,
        $productPricesDetailsId,
        $redeemerQuantity,
        $bookings = []
    ) {
        $this->cartItemId = $cartItemId;
        $this->productPricesDetailsId = $productPricesDetailsId;
        $this->redeemerQuantity = $redeemerQuantity;
        $this->bookings = $bookings;
    }

    public function toArray(): array
    {
        $bookingData = [];
        foreach ($this->bookings as $booking) {
            $bookingData[] = $booking->toArray();
        }


        return [
            "productPricesDetailsId" => $this->productPricesDetailsId,
            "redeemerQty" => $this->redeemerQuantity,
            "bookings" => $bookingData,
        ];
    }
}
