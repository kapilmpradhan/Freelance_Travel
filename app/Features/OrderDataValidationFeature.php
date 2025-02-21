<?php

namespace App\Features;

use Laravel\Pennant\Feature;

class OrderDataValidationFeature
{
    public static function isEnabled()
    {
        return Feature::for(null)->active('order-data-validation-feature');
    }
}
