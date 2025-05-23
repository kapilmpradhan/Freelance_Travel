<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Jobs\SendAccountDeletionEmail;
use App\Jobs\SubscribeToFCMTopic;
use App\Jobs\UnsubscribeFromFCMTopic;
use App\Logging\Logger;
use App\Models\FirebaseFcmToken;
use App\Models\User;
use Carbon\Carbon;
use Exception;

class UserService
{
    public static function updateUserProfile(User $user, mixed $data)
    {
        try {
            $user->update($data);
            $userResource = new UserResource();
            $responseData = $userResource->userDetail($user);
            return ServiceResponse::success($responseData);
        } catch (Exception $e) {
            $errorMessage = "User profile update failed";
            Logger::error(message: $errorMessage, exception: $e, extra: ['data' => $data]);
            throw new ServiceException($errorMessage);
        }
    }

    public static function checkIfUserContainsLeadCustomerDetail($user)
    {
        if (
            $user->first_name
            && $user->last_name
            && $user->date_of_birth
            && $user->phone_number
            && $user->post_code
            && $user->country_code
        ) {
            return ServiceResponse::success();
        }

        return ServiceResponse::notFound();
    }

    public static function deleteUserTemporarily($user)
    {
        $user->is_temporarily_deleted = true;
        $user->deletion_date = Carbon::now();
        $user->save();

        SendAccountDeletionEmail::dispatch($user);

        return ServiceResponse::success();
    }

    public static function checkIfFcmTokenExistsForUser($user, $token)
    {
        $fcmTokenExist = FirebaseFcmToken::where('user_id', $user->uuid)
                                    ->where('token', $token)
                                    ->exists();
        if ($fcmTokenExist) {
            return ServiceResponse::success();
        }

        return ServiceResponse::notFound();
    }

    public static function addFcmToken(User $user, string $token, string|null $clientUserAgent = null)
    {
        FirebaseFcmToken::create([
            'user_id' => $user->uuid,
            'token' => $token,
            'client_user_agent' => $clientUserAgent
        ]);

        SubscribeToFCMTopic::dispatch($token, 'all');

        return ServiceResponse::success();
    }

    public static function removeFcmToken($user, $token)
    {
        $token = FirebaseFcmToken::where('user_id', $user->uuid)
                                ->where('token', $token)
                                ->first();

        UnsubscribeFromFCMTopic::dispatch($token->token, 'all');

        $token->delete();
        return ServiceResponse::success();
    }
}
