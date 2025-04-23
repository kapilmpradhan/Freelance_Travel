<?php

namespace App\DTOs;

class RedeemerProductsOrderData
{
    public $redeemerId;
    public $productPricesDetailsId;
    public $redeemerQuantity;
    public $datePriceCacheId;
    public array $bookings;

    public function __construct(
        $redeemerId,
        $productPricesDetailsId,
        $redeemerQuantity,
        $datePriceCacheId,
        $bookings = []
    ) {
        $this->redeemerId = $redeemerId;
        $this->productPricesDetailsId = $productPricesDetailsId;
        $this->redeemerQuantity = $redeemerQuantity;
        $this->datePriceCacheId = $datePriceCacheId;
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
