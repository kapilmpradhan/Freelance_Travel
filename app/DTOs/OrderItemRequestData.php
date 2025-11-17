<?php

namespace App\DTOs;

class OrderItemRequestData
{
    public $product;
    public $fareprices;
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
        $fareprices,
        $productLastUpdate,
        $productBookingDetails,
        $productAvailabilities,
        $bookingData,
        $quantity = null,
        $timeId = null,
        $commences = null,
        $productPriceDetailsId = null,
    ) {
        $productJson = $product->json;
        $productJson['faresprices'] = $fareprices->json;
        $product->json = $productJson;

        $this->product = $product;
        $this->fareprices = $fareprices;
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
