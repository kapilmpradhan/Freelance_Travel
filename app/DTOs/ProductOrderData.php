<?php

namespace App\DTOs;

class ProductOrderData
{
    public $productPricesDetailsId;
    public $quantity;
    public $datePriceCacheId;
    public $cartItemIds = [];

    public function __construct(
        $productPricesDetailsId,
        $quantity,
        $datePriceCacheId,
        array $cartItemIds
    ) {
        $this->productPricesDetailsId = $productPricesDetailsId;
        $this->quantity = $quantity;
        $this->datePriceCacheId = $datePriceCacheId;
        $this->cartItemIds = $cartItemIds;
    }
    public function toArray(): array
    {
        return [
            "productPricesDetailsId" => $this->productPricesDetailsId,
            "qty" => $this->quantity,
            "datePriceCacheId" => $this->datePriceCacheId
        ];
    }
}
