<?php

namespace App\Services;

interface IEmailService
{
    public function sendMail(array $data): void;
}