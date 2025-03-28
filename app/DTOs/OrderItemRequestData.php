<?php

namespace App\DTOs;

class OrderItemRequestData
{
    public $product;
    public $productLastUpdate;
    public $productBookingDetails;
    public $productAvailabilities;
    public array $bookingData;
    public $quantity;
    public $timeId;
    public $commences;
    public $productPriceDetailsId;

    public function __construct(
        $product,
        $productLastUpdate,
        $productBookingDetails,
        $productAvailabilities,
        $bookingData,
        $quantity = null,
        $timeId = null,
        $commences = null,
        $productPriceDetailsId = null,
    ) {
        $this->product = $product;
        $this->productLastUpdate = $productLastUpdate;
        $this->productBookingDetails = $productBookingDetails;
        $this->productAvailabilities = $productAvailabilities;
        $this->bookingData = $bookingData;
        $this->quantity = $quantity;
        $this->timeId = $timeId;
        $this->commences = $commences;
        $this->productPriceDetailsId = $productPriceDetailsId;
    }
}
