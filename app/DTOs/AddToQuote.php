<?php

namespace App\DTOs;

class AddToQuote
{
    public bool $isNew;
    public ?string $quoteId;
    public ?string $title;

    public function __construct(bool $isNew, ?string $quoteId, ?string $title)
    {
        $this->isNew = $isNew;
        $this->quoteId = $quoteId;
        $this->title = $title;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['isNew'] ?? false,
            $data['quoteId'] ?? null,
            $data['title'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'isNew' => $this->isNew,
            'quoteId' => $this->quoteId,
            'title' => $this->title,
        ];
    }

    public static function new(string $title): self
    {
        return new self(true, null, $title);
    }

    public static function existing(string $quoteId): self
    {
        return new self(false, $quoteId, null);
    }
}
