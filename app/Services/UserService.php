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

            Logger::debug('User profile updated successfully', [
                'log_file' => config('logging.log_files.user_profile'),
                'user_id' => $user->uuid,
                'user_email' => $user->email,
                'action' => 'profile_update',
                'updated_fields' => implode(',', array_keys($data)),
            ]);

            return ServiceResponse::success($responseData);
        } catch (Exception $e) {
            $errorMessage = "User profile update failed";
            Logger::error(
                message: $errorMessage,
                exception: $e,
                extra: ['data' => $data],
                data: [
                    'log_file' => config('logging.log_files.errors'),
                    'user_id' => $user->uuid,
                    'user_email' => $user->email,
                    'action' => 'profile_update_failed',
                ]
            );
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

        Logger::debug('User account marked for deletion', [
            'log_file' => config('logging.log_files.user_activity'),
            'user_id' => $user->uuid,
            'user_email' => $user->email,
            'action' => 'account_deletion_requested',
            'deletion_date' => $user->deletion_date->toDateTimeString(),
        ]);

        SendAccountDeletionEmail::dispatch($user, app('platform'));

        return ServiceResponse::success();
    }

    public static function checkIfFcmTokenExistsForUser($user, $token)
    {
        $fcmTokenExist = FirebaseFcmToken::where('user_id', $user->uuid)
                            ->where('platform', app('platform'))
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
            'client_user_agent' => $clientUserAgent,
            'platform' => app('platform')
        ]);

        Logger::debug('FCM token added for user', [
            'log_file' => config('logging.log_files.fcm_subscription'),
            'user_id' => $user->uuid,
            'user_email' => $user->email,
            'action' => 'fcm_token_added',
            'platform' => app('platform'),
        ]);

        SubscribeToFCMTopic::dispatch($user, $token, 'all');
        SubscribeToFCMTopic::dispatch($user, $token, app('platform'));

        return ServiceResponse::success();
    }

    public static function removeFcmToken($user, $token)
    {
        $fcmToken = FirebaseFcmToken::where('user_id', $user->uuid)
                                ->where('token', $token)
                                ->first();

        Logger::debug('FCM token removed for user', [
            'log_file' => config('logging.log_files.fcm_subscription'),
            'user_id' => $user->uuid,
            'user_email' => $user->email,
            'action' => 'fcm_token_removed',
            'platform' => app('platform'),
        ]);

        UnsubscribeFromFCMTopic::dispatch($user, $fcmToken->token, 'all');
        UnsubscribeFromFCMTopic::dispatch($user, $fcmToken->token, app('platform'));

        $fcmToken->delete();
        return ServiceResponse::success();
    }
}
