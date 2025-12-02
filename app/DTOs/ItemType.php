<?php

namespace App\DTOs;

class ItemType
{
    public $agentBranchCode;
    public $type;
    public $typeId;
    public $subTypeId;
    public $data;
    public $isCart = false;
    public $isSession = false;
    public $isCartItem = false;
    public $isGroup = false;
    public $isQuote = false;
    public $isQuoteItem = false;
    public $isDirect = false;
    public $isProduct = false;
    public $isDry = false;
    public $forDiscount = false;

    public function __construct(
        string $type,
        null|int|string $typeId = null,
        null|int|string $subTypeId = null,
        $data = null
    ) {
        if (app('agentType') ?? null) {
            $this->agentBranchCode = app('agentType')->agent->branch_code;
        }
        $this->type = $type;
        $this->typeId = $typeId;
        $this->subTypeId = $subTypeId;
        $this->data = $data;
    }

    public static function cart()
    {
        $cart = new ItemType('cart');
        $cart->isCart = true;
        return $cart;
    }

    public static function session($sessionId)
    {
        $cart = new ItemType('session');
        $cart->isSession = true;
        $cart->typeId = $sessionId;
        return $cart;
    }

    public static function cartItem(string|null $typeId)
    {
        $cartItem = new ItemType('cartItem', $typeId);
        $cartItem->isCartItem = true;
        return $cartItem;
    }

    public static function group(string|null $typeId, string|null $subTypeId = null)
    {
        $group = new ItemType('group', $typeId, $subTypeId);
        $group->isGroup = true;
        return $group;
    }

    public static function product(string|null $typeId, string|null $subTypeId = null)
    {
        $group = new ItemType('product', $typeId, $subTypeId);
        $group->isProduct = true;
        return $group;
    }

    public static function quote(string|null $typeId)
    {
        $quote = new ItemType('quote', $typeId);
        $quote->isQuote = true;
        return $quote;
    }

    public static function quoteItem(string|null $typeId, string|null $subTypeId = null)
    {
        $quote = new ItemType('quote', $typeId, $subTypeId);
        $quote->isQuoteItem = true;
        return $quote;
    }

    public static function direct()
    {
        $direct = new ItemType('direct');
        $direct->isDirect = true;
        return $direct;
    }

    public static function dry($data = null)
    {
        $dry = new ItemType('dry');
        $dry->isDry = true;
        $dry->data = $data;
        return $dry;
    }

    public static function discount()
    {
        $itemType = new ItemType('discount');
        $itemType->forDiscount = true;
        return $itemType;
    }
}
