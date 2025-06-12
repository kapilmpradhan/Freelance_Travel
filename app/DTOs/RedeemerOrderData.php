<?php

namespace App\DTOs;

class RedeemerOrderData
{
    public $redeemerId;
    public $title;
    public $email;
    public $firstName;
    public $lastName;
    public $phoneNumber;
    public $countryCode;
    public $dateOfBirth;
    public $postalCode;
    public array $products;

    public function __construct(
        $redeemerId,
        $title,
        $email,
        $firstName,
        $lastName,
        $phoneNumber,
        $countryCode,
        $dateOfBirth,
        $postalCode,
        $products = [],
    ) {
        $this->redeemerId = $redeemerId;
        $this->title = $title;
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phoneNumber = $phoneNumber;
        $this->countryCode = $countryCode;
        $this->dateOfBirth = $dateOfBirth;
        $this->postalCode = $postalCode;
        $this->products = $products;
    }

    public function toArray(): array
    {
        $productData = [];
        foreach ($this->products as $product) {
            $productData[] = $product->toArray();
        }
        $data = [
            "title" => $this->title,
            "emailAddress" => $this->email,
            "firstName" => $this->firstName,
            "lastName" => $this->lastName,
            "phone" => $this->phoneNumber,
            "redeemerCountry" => $this->countryCode,
            "dateOfBirth" => $this->dateOfBirth,
            "postcode" => $this->postalCode,
            "products" => $productData
        ];

        return $data;
    }
}
