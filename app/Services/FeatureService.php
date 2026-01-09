<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

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

    /**
     * Check if a user has permission for a feature.
     * Always returns true if user is a tester.
     */
    public static function isFeatureEnabledForUser(string $featureName, User|null $user): bool
    {
        $isFeatureEnabledForUser = Feature::for($user)->active($featureName);
        if ($isFeatureEnabledForUser) {
            return true;
        }

        $isFeatureEnabledForTester = Feature::for(null)->active("{$featureName}_enabled_for_tester");
        if ($isFeatureEnabledForTester) {
            $isTester = Feature::for($user)->active('tester');
            return $isTester;
        }

        return false;
    }
}
