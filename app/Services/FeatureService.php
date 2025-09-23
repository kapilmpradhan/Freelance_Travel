<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FeatureService
{
    public static function isFeatureEnabled($featureName, $scope = null)
    {
        $feature = DB::table('features')
            ->where('name', $featureName)
            ->where('scope', $scope ? $scope : '__laravel_null')
            ->first();

        $value = $feature ? ($feature->value == 'true' ? true : false) : false;
        return $feature && $value;
    }
}
