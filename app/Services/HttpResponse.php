<?php

namespace App\Services;

use Exception;

class HttpResponse
{
    public int $responseCode;
    public mixed $data;
    public ?string $message;

    public function __construct(int $responseCode, mixed $data = null, ?string $message = null)
    {
        $this->responseCode = $responseCode;
        $this->data = $data;
        $this->message = $message;
    }

    public function isError()
    {
        return !$this->isSuccess();
    }

    public function isSuccess()
    {
        return $this->responseCode == 200 || $this->responseCode == 201;
    }

    public static function success(mixed $data = null, int $responseCode = 200): self
    {
        return new self($responseCode, $data);
    }

    public static function failed(string $message, $responseCode, mixed $data = null): self
    {
        return new self($responseCode, $data, $message);
    }
}
