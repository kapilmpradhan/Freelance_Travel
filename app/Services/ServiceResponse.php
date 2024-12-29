<?php

namespace App\Services;

use Exception;

class ServiceResponse
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

    public static function success(mixed $data = null, ?string $message = 'Operation successful'): self
    {
        return new self(200, $data, $message);
    }

    public static function created(mixed $data = null, ?string $message = 'Operation successful'): self
    {
        return new self(201, $data, $message);
    }

    public static function badRequest(?string $message = 'Bad request', mixed $data = null): self
    {
        return new self(400, $data, $message);
    }

    public static function unauthorized(?string $message = 'Unauthorized', mixed $data = null): self
    {
        return new self(401, $data, $message);
    }

    public static function notFound(?string $message = 'Not found', mixed $data = null): self
    {
        return new self(404, $data, $message);
    }
}
