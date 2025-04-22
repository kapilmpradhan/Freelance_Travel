<?php

namespace App\DTOs;

class ItemType
{
    public $type;
    public $typeId;
    public $isCart = false;
    public $isCartItem = false;
    public $isGroup = false;
    public $isQuote = false;
    public $isDirect = false;
    public $isProduct = false;

    public function __construct(string $type, null|int|string $typeId = null)
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

    public static function cartItem(string|null $typeId)
    {
        $cartItem = new ItemType('cartItem', $typeId);
        $cartItem->isCartItem = true;
        return $cartItem;
    }

    public static function group(string|null $typeId)
    {
        $group = new ItemType('group', $typeId);
        $group->isGroup = true;
        return $group;
    }

    public static function product(string|null $typeId)
    {
        $group = new ItemType('product', $typeId);
        $group->isProduct = true;
        return $group;
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
