<?php

namespace App\DTOs;

class RedeemerProductsOrderData
{
    public $productPricesDetailsId;
    public $redeemerQuantity;
    public array $bookings;

    public function __construct(
        $productPricesDetailsId,
        $redeemerQuantity,
        $bookings = [],
    ) {
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
            "bookings" => $bookingData
        ];
    }
}
