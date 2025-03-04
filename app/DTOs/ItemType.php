<?php

namespace App\DTOs;

class ItemType
{
    public $type;
    public $typeId;
    public $isCart = false;
    public $isQuote = false;
    public $isDirect = false;

    public function __construct(string $type, null|int $typeId = null)
    {
        $this->type = $type;
        $this->typeId = $typeId;
    }

    public static function cart()
    {
        $cart = new ItemType('cart');
        $cart->isCart = true;
        return $cart;
    }

    public static function quote(string|null $typeId)
    {
        $quote = new ItemType('quote', $typeId);
        $quote->isQuote = true;
        return $quote;
    }

    public static function direct()
    {
        $direct = new ItemType('direct');
        $direct->isDirect = true;
        return $direct;
    }
}
