<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtService;
use App\Services\OtpService;
use App\Jobs\SendProfileEmailOtp;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\ServiceException;
use App\Services\UserService;
use App\Logging\Logger;
use App\Services\RedeemerService;
use App\Services\FcmService;
use App\Services\FeatureService;
use Exception;
use Laravel\Pennant\Feature;

class UserController extends BaseController
{
    public function userSignupEmail(Request $request, User $user, UserResource $userResource)
    {
        $data = $request->all(); // Retrive request data
        $validate = Validator::make($data, $user->emailSignupRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        $data['sso_type'] = 'email'; // Add email sso to the array
        $new_user = $user->storeUser($data);
        SendProfileEmailOtp::dispatch($new_user->uuid, app('platform'));
        $return_data = $userResource->userDetail($new_user);

        return $this->sendResponse('successfully', $return_data);
    }

    public function sendVerificationEmail(Request $request)
    {
        $user = $request->user;
        if ($user->is_email_verified) {
            return $this->sendError('Email already verified');
        }
        SendProfileEmailOtp::dispatch($user->uuid, app('platform'));

        return $this->sendResponse('Verification email sent');
    }

    public function userLoginEmail(Request $request, User $user, JwtService $jwtService)
    {
        $validate = Validator::make($request->all(), $user->emailLoginRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        // Retrieve the user by email
        $user = User::getAllUsers()
                    ->where('email', $request->email)
                    ->first();

        if ($user && $user->is_permanently_deleted == true) {
            return $this->sendError('This account was permanently deleted');
        }

        // Check if the user exists and the password is correct
        if ($user && Hash::check($request->password, $user->password)) {
            if ($user->is_temporarily_deleted == true) {
                $user->is_temporarily_deleted = false;
                $user->deletion_date = null;
            }
            $user->last_login = now();
            $user->save();

            $accessToken = $jwtService->generateAccessToken($user);
            $refreshToken = $jwtService->generateRefreshToken(
                $user->uuid,
                $request->header('User-Agent')
            );

            $data = [
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken
            ];
            return $this->sendResponse('Access and Refresh tokens', $data);
        } else {
            return $this->sendError('Invalid Credentials');
        }
    }

    public function changePassword(Request $request, User $user)
    {
        $data = $request->all();
        $validate = Validator::make($data, $user->changePasswordRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        // Retrieve the user by email
        $user = User::where('email', $request->user->email)->first();

        if (!$user) {
            return $this->sendError('User not found');
        } elseif ($user->sso_type != 'email') {
            return $this->sendError('Email signed up user can only change password.');
        } elseif (!Hash::check($data['current_password'], $user->password)) {
            return $this->sendError('Incorrect current password');
        } elseif (Hash::check($data['new_password'], $user->password)) {
            return $this->sendError('New and old password cannot be same');
        } else {
            $user->password = Hash::make($data['new_password']);
            $user->save();
            return $this->sendResponse('Password changed');
        }
    }

    public function updatePassword(Request $request, User $user)
    {
        $data = $request->all();
        $validate = Validator::make($data, $user->resetPasswordRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        $user = $user->getSsoEmailUser($data['email']);

        if (!$user) {
            return $this->sendError('Invalid OTP/email');
        }

        $is_otp_valid = OtpService::checkOtpForPasswordUpdate($user, $data['otp']);

        if ($is_otp_valid['success'] == false) {
            return $this->sendError($is_otp_valid['error']);
        }

        try {
            // user did not complete verify email,
            // lost password but update pasword from otp
            // so set email to verified
            if (!$user->is_email_verified && $user->sso_type === 'email') {
                $user->is_email_verified = true;
                $user->save();
            }
            $user->updatePassword($data['new_password']);
            return $this->sendResponse('Password changed successfully');
        } catch (Exception $e) {
            $errorMessage = "Failed to reset password";
            Logger::error($errorMessage, $e);
            return $this->sendError('Password reset failed');
        }
    }

    public function updateNickname(Request $request)
    {
        $user = $request->user();
        $data = $request->all();
        $validator = Validator::make($data, ['nickname' => 'required|string|max:50']);
        if ($validator->fails()) {
            return $this->sendError('Invalid nickname', $validator->errors());
        }

        try {
            $user->nickname = $data['nickname'];
            $user->save();
            return $this->sendResponse('Nickname updated');
        } catch (Exception $e) {
            Logger::error('Unable to update nickname', $e);
            return $this->sendError('Unable to update nickname');
        }
    }

    public function userDetail(Request $request, UserResource $userResource)
    {
        $user = $request->get('user');

        if (!($user)) {
            return $this->sendError('Account unauthenticated');
        }

        $account_data = $userResource->userDetail($user);
        return $this->sendResponse('Account details', $account_data);
    }

    public function accessTokenRegenerate(Request $request, JwtService $jwtService)
    {
        $data = $request->all();

        $validate = Validator::make(
            $data,
            [
                "user_id" => "required",
                "refresh_token" => "required"
            ]
        );

        if ($validate->fails()) {
            return $this->sendError('Error occured', $validate->errors());
        }

        $user_id = $data['user_id'];
        $refresh_token = $data['refresh_token'];

        $validateRefreshTokenResult = $jwtService->validateRefreshToken($refresh_token, $user_id);

        if (!$validateRefreshTokenResult) {
            return $this->sendError('Invalid refresh token');
        }

        $data = [
            'accessToken' => $validateRefreshTokenResult
        ];

        return $this->sendResponse('New access token', $data);
    }

    public function updateProfile(Request $request, UserResource $userResource)
    {
        $data = $request->all();
        $user = $request->user;

        $validator = Validator::make($data, $user->updateProfileRule());
        if ($validator->fails()) {
            return $this->sendError('Profile update failed', $validator->errors());
        }

        $validatedData = $validator->validate();

        try {
            $updateUserProfileResponse = UserService::updateUserProfile($user, $validatedData);
            RedeemerService::addOrUpdatePrimaryRedeemer($user);
            return $this->sendResponseFromService($updateUserProfileResponse);
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }

    public function deleteUserTemporarily(Request $request)
    {
        $user = $request->user();

        $deleteResponse = UserService::deleteUserTemporarily($user);
        return $this->sendResponseFromService($deleteResponse);
    }

    public function addFcmToken(Request $request)
    {
        $data = $request->all();
        $data['userAgentInfo'] = $request->header('User-Agent', null);
        $user = $request->user;

        $validator = Validator::make(
            $data,
            [
                'fcmToken' => 'string|required',
                'userAgentInfo' => 'string|nullable'
            ]
        );
        if ($validator->fails()) {
            return $this->sendError('Add FCM token failed', $validator->errors());
        }
        $validatedData = $validator->validate();

        $checkFcmResponse = UserService::checkIfFcmTokenExistsForUser($user, $validatedData['fcmToken']);
        if ($checkFcmResponse->isSuccess()) {
            return $this->sendResponseFromService($checkFcmResponse);
        }

        $addFcmToken = UserService::addFcmToken(
            user: $user,
            token: $validatedData['fcmToken'],
            clientUserAgent: $validatedData['userAgentInfo']
        );
        return $this->sendResponseFromService($addFcmToken);
    }

    public function removeFcmToken(Request $request, $fcmToken)
    {
        $user = $request->user;
        $fcmService = new FcmService();

        $checkFcmResponse = UserService::checkIfFcmTokenExistsForUser($user, $fcmToken);
        if ($checkFcmResponse->isSuccess()) {
            $removeFcmToken = UserService::removeFcmToken($user, $fcmToken);
            $fcmService->unsubscribeTokensFromTopic($user->uuid, [$fcmToken]);
            return $this->sendResponseFromService($removeFcmToken);
        }

        return $this->sendResponse('Success');
    }

    public function showHidePoints(Request $request)
    {
        $user = $request->user;
        $user->is_points_displayed = !$user->is_points_displayed;
        $user->save();

        return $this->sendResponse('Points visibility toggled');
    }

    public function userMetaData(Request $request)
    {
        $user = auth()->user();
        $agentType = app('agentType');
        $isTestUser = Feature::for($user)->active('tester');
        $isDefaultAgent = $agentType->isDefaultAgent;
        $isPointsAgent = $agentType->isPointsAgent;
        $isCommissionAgent = $agentType->isCommissionAgent;

        $isOrderTabbedEnabledForAll = FeatureService::isFeatureEnabled('order-tabbed-view');
        $isPaymentLinkSharingEnabledForAll = FeatureService::isFeatureEnabled('payment-link-sharing');

        $isOrderTabEnabled = !$isDefaultAgent && ($isOrderTabbedEnabledForAll || $isTestUser);
        $isPaymentLinkSharingEnabled = $isPaymentLinkSharingEnabledForAll || $isTestUser;

        $data = [
            'is_order_tab_enabled' => $isOrderTabEnabled,
            'is_payment_link_sharing_enabled' => $isPaymentLinkSharingEnabled,
            'is_test_user' => $isTestUser,
            'is_default_agent' => $isDefaultAgent,
            'is_points_agent' => $isPointsAgent,
            'is_commission_agent' => $isCommissionAgent
        ];

        return $this->sendResponse('User meta data', $data);
    }
}
