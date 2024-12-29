<?php

namespace App\Services;

use Exception;

class ServiceException extends Exception
{
    public mixed $data;

    public function __construct(string $message, mixed $data = null, int $code = 500)
    {
        parent::__construct($message, $code);
        $this->data = $data;
    }

    public function toServiceResponse()
    {
        return new ServiceResponse(
            responseCode: $this->code,
            data: $this->data,
            message: $this->message,
        );
    }
}
