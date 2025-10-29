<?php

namespace App\Services;

interface IEmailService
{
    public function sendMail(array $data): string;
    public function sendMailV2(array $data): ServiceResponse;
}
