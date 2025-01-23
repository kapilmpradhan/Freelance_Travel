<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Logging\Logger;
use App\Models\User;
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
            Logger::error(message: $errorMessage, exception: $e);
            throw new ServiceException($errorMessage);
        }
    }
}
