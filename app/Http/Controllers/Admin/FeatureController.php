<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use App\Services\FeatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Pennant\Feature;

class FeatureController extends BaseController
{
    /**
     * Ensure a feature exists in the database.
     */
    private function ensureFeatureExists(string $featureName): void
    {
        $exists = DB::table('features')
            ->where('name', $featureName)
            ->where('scope', '__laravel_null')
            ->exists();

        if (!$exists) {
            DB::table('features')->insert([
                'name' => $featureName,
                'scope' => '__laravel_null',
                'value' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Activate a feature for a specific user.
     */
    public function activateFeatureForUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userEmail' => 'required|email',
            'featureName' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $user = User::where('email', $request->userEmail)->first();

        if (!$user) {
            return $this->sendError('User not found.', [], 404);
        }

        Feature::for($user)->activate($request->featureName);

        return $this->sendResponse("Feature '{$request->featureName}' activated for user '{$request->userEmail}'.");
    }

    /**
     * Deactivate a feature for a specific user.
     */
    public function deactivateFeatureForUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userEmail' => 'required|email',
            'featureName' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $user = User::where('email', $request->userEmail)->first();

        if (!$user) {
            return $this->sendError('User not found.', [], 404);
        }

        Feature::for($user)->deactivate($request->featureName);

        return $this->sendResponse("Feature '{$request->featureName}' deactivated for user '{$request->userEmail}'.");
    }

    /**
     * Activate a feature for all users.
     */
    public function activateFeatureForAll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'featureName' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $isTester = $request->has('isTester') ? (bool) $request->isTester : false;
        $this->ensureFeatureExists("{$request->featureName}_enabled_for_tester");
        if ($isTester) {
            Feature::activateForEveryone("{$request->featureName}_enabled_for_tester");
            return $this->sendResponse("Feature '{$request->featureName}' activated for all testers.");
        }

        Feature::activateForEveryone($request->featureName);

        return $this->sendResponse("Feature '{$request->featureName}' activated for all users.");
    }

    /**
     * Deactivate a feature for all users.
     */
    public function deactivateFeatureForAll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'featureName' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $isTester = $request->has('isTester') ? (bool) $request->isTester : false;
        $this->ensureFeatureExists("{$request->featureName}_enabled_for_tester");
        if ($isTester) {
            Feature::deactivateForEveryone("{$request->featureName}_enabled_for_tester");
            return $this->sendResponse("Feature '{$request->featureName}' deactivated for all testers.");
        }

        Feature::deactivateForEveryone($request->featureName);

        return $this->sendResponse("Feature '{$request->featureName}' deactivated for all users.");
    }

    /**
     * Set or unset a user as tester.
     */
    public function setUserTester(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userEmail' => 'required|email',
            'isTester' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $user = User::where('email', $request->userEmail)->first();

        if (!$user) {
            return $this->sendError('User not found.', [], 404);
        }

        if ($request->isTester) {
            Feature::for($user)->activate('tester');
            $status = 'set as';
        } else {
            Feature::for($user)->deactivate('tester');
            $status = 'removed as';
        }

        return $this->sendResponse("User '{$request->userEmail}' has been {$status} tester.");
    }

    /**
     * Get all features with their status for a user.
     */
    public function getUserFeatures(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userEmail' => 'email',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $user = null;
        if ($request->has('userEmail')) {
            $user = User::where('email', $request->userEmail)->first();

            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }
        }

        // Get all unique feature names from the features table
        $featureNames = DB::table('features')
            ->when(!$user, function ($query) {
                $query->where('scope', '__laravel_null');
            })
            ->when($user, function ($query) use ($user) {
                $query->whereNot('scope', '__laravel_null');
            })
            ->select('name')
            ->distinct()
            ->pluck('name');

        $features = [];
        foreach ($featureNames as $featureName) {
            $features[] = [
                'name' => $featureName,
                'active' => FeatureService::isFeatureEnabledForUser($featureName, $user),
            ];
        }

        return $this->sendResponse('User features retrieved successfully.', $features);
    }
}
