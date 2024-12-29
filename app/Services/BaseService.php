<?php

namespace App\Services;

class BaseService
{
    public static function stringToDate(string $dateStr)
    {
        return date('Y-m-d', strtotime($dateStr));
    }
}
