<?php

namespace App\DTOs;

class OrderItemRequestData
{
    public $product;
    public $productLastUpdate;
    public $productBookingDetails;
    public $productAvailabilities;
    public array $bookingData;

    public function __construct(
        $product,
        $productLastUpdate,
        $productBookingDetails,
        $productAvailabilities,
        $bookingData,
    ) {
        $this->product = $product;
        $this->productLastUpdate = $productLastUpdate;
        $this->productBookingDetails = $productBookingDetails;
        $this->productAvailabilities = $productAvailabilities;
        $this->bookingData = $bookingData;
    }
}
