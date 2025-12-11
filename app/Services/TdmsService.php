<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use App\Logging\Logger;
use App\Models\UserAgent;

class TdmsService
{
    private const CURRENCY_ID_AUD = 297;
    private const SUB_SYSTEM_TYPE_CASHBACK = 'FIT';

    public static function getAgentToken(
        string $username,
        string $password,
        string $userId = null
    ): ServiceResponse {
        $url = config('vars.tdms_api_url') . '/agentToken';

        $data = [
            'username' => $username,
            'password' => $password
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
        ->withBody(json_encode($data))
        ->post($url);

        if (!$response->ok()) {
            Logger::error("Failed to get agent token", extra: [
                'userId' => $userId,
                'username' => $username,
                'password' => $password,
                'responseStatusCode' => $response->status(),
            ]);
            return ServiceResponse::badRequest('Failed to get Agent details');
        }

        return ServiceResponse::success($response->json());
    }

    public static function getAgentDetails(string $agentToken): ServiceResponse
    {
        $url = config('vars.tdms_api_url') . '/agentDetails';
        $response = Http::asJson()
            ->withToken($agentToken)
            ->get($url);

        if ($response->failed()) {
            Logger::error("Failed to get Agent details");
            return ServiceResponse::badRequest('Failed to get Agent details');
        }

        return ServiceResponse::success($response->json());
    }

    public static function getCustomerLastOrderBranch($customerEmail)
    {
        $url = config('vars.tdms_customer_api_url') . "?email={$customerEmail}";

        // Credentials from config
        $username = config('vars.tdms_customer_api_username');
        $password = config('vars.tdms_customer_api_password');

        $response = Http::withBasicAuth($username, $password)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->get($url);

        // Check if the request was successful
        if ($response->successful()) {
            $data = $response->json();
            if ($data['orders']) {
                return substr(end($data['orders'])['bookingReference'], 0, 3);
            }
        }
        return null;
    }

    public static function getBookingRefrence($agentToken, $getCached = true)
    {
        if ($getCached) {
            $cachedBookingReference = UserCacheService::getCachedBookingReference();
            if ($cachedBookingReference->isSuccess()) {
                return $cachedBookingReference->data;
            }
        }
        $url = config('vars.tdms_api_url') . "/bookingreference";

        $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->post($url);

        $data = $response->json();
        if ($response->successful()) {
            return $data['bookingReference'];
        }
        return null;
    }

    public static function getPaymentMethods($agentToken, $getCached = true)
    {
        if ($getCached) {
            $cachePaymentMethodsResponse = UserCacheService::getCachedPaymentMethods();
            if ($cachePaymentMethodsResponse->isSuccess()) {
                return $cachePaymentMethodsResponse->data;
            }
        }

        $url = config('vars.tdms_api_url') . "/agent/paymentmethods";
        $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->get($url);

        // Check if the request was successful
        if ($response->successful()) {
            $data = $response->json();
            if (!$data['paymentMethods']) {
                return null;
            }
            return $data['paymentMethods'];
        }
        return null;
    }

    public static function placeOrder($agentToken, $data, $userId, $previewMode = null)
    {
        $url = config('vars.tdms_api_url') . "/order";

        $query = [];
        if (!is_null($previewMode)) {
            $query['isPreview'] = $previewMode;
        }

        $finalUrl = $url . (!empty($query) ? '?' . http_build_query($query) : '');

        $response = Http::timeout(60)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->withBody(json_encode($data))
            ->post($finalUrl);

        $data = $response->json();
        if ($response->successful()) {
            return ServiceResponse::success(
                message: "Order placed",
                data: $data
            );
        }
        Logger::error("Failed to create order", extra: [
            'userId' => $userId,
            'requestData' => $data,
            'responseData' => [
                "statusCode" => $response->status(),
                "headers" => $response->headers(),
                "body" => $response->body(),
            ]
        ]);
        return ServiceResponse::badRequest(
            message: "Failed to create order",
            data: $data
        );
    }

    public static function getPaymentGatewayUri($agentToken, $bookingReference, $paymentAmount, $returnUrl)
    {
        $url = config('vars.tdms_api_url') . "/pgw/authenticate/{$bookingReference}";

        $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->withQueryParameters([
                "paymentAmount" => $paymentAmount,
                "returnUrl" => 'https://' . $returnUrl
            ])
            ->get($url);

        // Check if the request was successful
        $data = $response->json();
        if ($response->successful()) {
            return [
                "success" => true,
                "data" => $data
            ];
        }
        return [
            "success" => false,
            "error" => $data['statusText'],
            "message" => $data["message"]
        ];
    }

    public static function getCustomerBookings($customerEmail)
    {
        // Credentials from config
        $username = config('vars.tdms_customer_api_username');
        $password = config('vars.tdms_customer_api_password');

        $allOrders = [];
        $page = 1;
        while (true) {
            $url = config('vars.tdms_customer_api_url') . "?email=" . urlencode($customerEmail) . "&page=$page";
            $response = Http::withBasicAuth($username, $password)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->get($url);

            // Check if the request was successful
            $data = $response->json();
            if ($response->successful()) {
                $allOrders = array_merge($allOrders, $data['orders']);
                if ($data['has_more'] == false) {
                    break;
                } else {
                    $page++;
                    continue;
                }
            } else {
                Logger::error(
                    message: 'Error fetching customer orders',
                    extra: [
                        "customerEmail" => $customerEmail,
                        "responseData" => $data,
                        "page" => $page
                    ]
                );

                return ServiceResponse::badRequest('Unable to get customer bookings.');
            }
        }

        return ServiceResponse::success(
            data: ['orders' => $allOrders]
        );
    }

    public static function convertQuoteToOrder($agentToken, $bookingReference)
    {
        $url = config('vars.tdms_api_url') . "/order/{$bookingReference}/convertQuote";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->post($url);
        } catch (Exception $e) {
            Logger::error(
                message: 'Server error while converting quote to order',
                extra: ['bookingReference' => $bookingReference, 'exception' => $e]
            );
            throw new ServiceException('Server error while converting quote to order');
        }

        // Check if the request was successful
        $data = $response->json();
        if (isset($data['errors'])) {
            return ServiceResponse::badRequest(
                message: $data['message']
            );
        }

        if (!$response->successful()) {
            Logger::error(
                message: 'Error converting quote to order',
                extra: [
                    "bookingReference" => $bookingReference,
                    "responseData" => $data
                ]
            );
        }

        return ServiceResponse::success();
    }

    public static function checkIfCustomerOrderStatusIsOrder($agentToken, $orderId)
    {
        try {
            $url = config('vars.tdms_api_url') . "/customerOrderDetail";

            $response = Http::withQueryParameters([
                "searchOnlyStatus" => "Order",
                "orderId" => $orderId
            ])
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->get($url);
        } catch (Exception $e) {
            Logger::error(
                message: "Error while fetching customer order detail",
                extra: ["orderId" => $orderId, "exception" => $e]
            );
            throw new ServiceException(message: 'Server error while fetching customer order detail');
        }

        $responseStatus = $response->status();

        if ($responseStatus == 200) {
            return ServiceResponse::success();
        } elseif ($responseStatus == 404) {
            return ServiceResponse::notFound();
        } else {
            Logger::error(
                message: "Error while fetching customer order detail",
                extra: ["orderId" => $orderId, "response" => $response->json()]
            );
            return ServiceResponse::badRequest(message: 'Internal server error');
        }
    }

    public static function getUserOrders($agentToken)
    {
        try {
            $url = config('vars.tdms_api_url') . "/customerOrderDetail";

            $response = Http::withQueryParameters([
                "searchOnlyStatus" => "Order",
                "toDate" => Carbon::now()->addYears(3)->format('d-M-Y')
            ])
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->get($url);
        } catch (Exception $e) {
            Logger::error(
                message: "Error while fetching customer order detail",
                extra: ["exception" => $e]
            );
            throw new ServiceException(message: 'Server error while fetching customer order detail');
        }

        $responseStatus = $response->status();

        if ($responseStatus == 200) {
            return ServiceResponse::success($response->json());
        } elseif ($responseStatus == 404) {
            return ServiceResponse::success([]);
        } else {
            Logger::error(
                message: "Error while fetching customer order detail",
                extra: ["response" => $response->json()]
            );
            return ServiceResponse::badRequest(message: 'Internal server error');
        }
    }

    public static function customerOrderHistory(
        string $agentToken,
        string|null $customerEmail = null,
        ?Carbon $sinceDate = null
    ): ServiceResponse {
        $params = [
            "searchOnlyStatus" => "Order"
        ];

        if ($customerEmail) {
            $params['email'] = $customerEmail;
        }

        if ($sinceDate !== null) {
            $params['since'] = $sinceDate->toDateString();
        }

        try {
            $response = Http::asJson()
                ->withToken($agentToken)
                ->withQueryParameters($params)
                ->get(config('vars.tdms_api_url') . "/customerOrderDetail");
        } catch (Exception $e) {
            Logger::error(
                message: "Error while fetching customer order history",
                extra: array_merge($params, ["exception" => $e])
            );
            throw new ServiceException(message: 'Server error while fetching customer order history');
        }

        if ($response->ok()) {
            return ServiceResponse::success($response->json());
        }

        if ($response->notFound()) {
            return ServiceResponse::notFound();
        }

        Logger::error(
            message: "Error while fetching customer order history",
            extra: array_merge($params, ["response" => $response->json()])
        );

        return ServiceResponse::badRequest(message: 'Internal server error');
    }

    public static function validateOrderData($agentToken, $bookingReference, $orderData): ServiceResponse
    {
        $url = config('vars.tdms_api_url') . "/validateCart";

        $response = Http::timeout(120)->withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$agentToken}"
        ])
        ->withBody(json_encode($orderData))
        ->post($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data
            );
        } else {
            if (!isset($data['message'])) {
                Logger::error(
                    message: 'Error validating order data',
                    extra: [
                        "bookingReference" => $bookingReference,
                        "orderData" => $orderData,
                        "responseData" => $data
                    ]
                );
                throw new ServiceException(
                    message: 'Internal server error',
                    data: $data,
                    code: $response->status()
                );
            } else {
                return ServiceResponse::badRequest(
                    message: $data['message']
                );
            }
        }
    }

    public static function getCategoriesOfAType($type, $agentToken)
    {
        $url = config('vars.tdms_api_url') . "/categories/{$type}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            if (!isset($data['message'])) {
                Logger::error(
                    message: 'Error validating order data',
                    extra: [
                        "type" => $type,
                        "responseData" => $data
                    ]
                );
                throw new ServiceException(
                    message: 'Internal server error',
                    data: $data,
                    code: $response->status()
                );
            } else {
                return ServiceResponse::badRequest(
                    message: $data['message']
                );
            }
        }
    }

    public static function getCategoriesByType($type, $agentToken, $countryId = 20)
    {
        $url = config('vars.tdms_api_url') . "/categories/{$type}?countries={$countryId}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            if (!isset($data['message'])) {
                Logger::error(
                    message: 'Error validating order data',
                    extra: [
                        "type" => $type,
                        "responseData" => $data
                    ]
                );
                throw new ServiceException(
                    message: 'Internal server error',
                    data: $data,
                    code: $response->status()
                );
            } else {
                return ServiceResponse::badRequest(
                    message: $data['message']
                );
            }
        }
    }

    public static function getProductsByCategory($type, $typeId, $agentToken, $countryId = 20)
    {
        $url = config('vars.tdms_api_url') . "/products?{$type}={$typeId}&countries={$countryId}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            if (!isset($data['message'])) {
                Logger::error(
                    message: 'Error validating order data',
                    extra: [
                        "type" => $type,
                        "responseData" => $data
                    ]
                );
                throw new ServiceException(
                    message: 'Internal server error',
                    data: $data,
                    code: $response->status()
                );
            } else {
                return ServiceResponse::badRequest(
                    message: $data['message']
                );
            }
        }
    }

    public static function getProductsByMultipleCategories(
        array $categoriesIdByTypes,
        $agentToken,
        $countryId = 20,
        $recordStart = 0,
        $recordsLength = 10
    ) {
        $query = '';
        foreach ($categoriesIdByTypes as $type => $categoryIds) {
            $ids = implode(',', $categoryIds);
            $query = "$query$type=$ids" . '&';
        }
        $url = config('vars.tdms_api_url') .
            "/products?countries={$countryId}" .
            "&$query&records-start=$recordStart&records-length=$recordsLength" .
            "&sortBy=ranking&sortDirection=asc";

        $retryCount = 0;
        while ($retryCount < 3) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                "Authorization" => "Bearer {$agentToken}"
            ])->timeout(60)->get($url);

            if (!$response->successful()) {
                $retryCount += 1;
            } else {
                break;
            }
        }

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            if (!isset($data['message'])) {
                Logger::error(
                    message: 'Error while fetching products by multiple categories',
                    extra: [
                        "type" => $type,
                        "responseData" => $data
                    ]
                );
                throw new ServiceException(
                    message: 'Internal server error',
                    data: $data,
                    code: $response->status()
                );
            } else {
                return ServiceResponse::badRequest(
                    message: $data['message']
                );
            }
        }
    }

    public static function getCountries($agentToken)
    {
        $url = config('vars.tdms_api_url') . "/categories/countries";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            return ServiceResponse::badRequest(
                message: $data['message']
            );
        }
    }

    public static function getCountryRegions($agentToken, $countryId)
    {
        $url = config('vars.tdms_api_url') . "/categories/regions?countries={$countryId}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            return ServiceResponse::badRequest(
                message: $data['message']
            );
        }
    }

    public static function getProductsByRegion($agentToken, $countryId, $regionId)
    {
        $url = config('vars.tdms_api_url') .
            "/products?countries={$countryId}" .
            "&regions={$regionId}" .
            "&sortBy=ranking&sortDirection=asc";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            return ServiceResponse::badRequest(
                message: $data['message']
            );
        }
    }

    public static function getCategoriesByTypeAndSubtype($agentToken, $type, $subType, $subtypeId)
    {
        $url = config('vars.tdms_api_url') . "/categories/{$type}?{$subType}={$subtypeId}";

        $maxRetries = 3;
        $retryDelay = 1000;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                "Authorization" => "Bearer {$agentToken}"
            ])->get($url);

            if (!$response->successful()) {
                Logger::error(
                    message: 'Error fetching categories by type and subtype',
                    extra: [
                        "type" => $type,
                        "subType" => $subType,
                        "subtypeId" => $subtypeId,
                        "responseData" => $response->json()
                    ]
                );
                if ($attempt < $maxRetries - 1) {
                    usleep($retryDelay * 1000);
                    continue;
                } else {
                    throw new ServiceException(
                        message: 'Internal server error while fetching categories',
                        data: $response->json(),
                        code: $response->status()
                    );
                }
            }
        }

        $data = $response->json();

        if ($response->status() == 200) {
            $data = $data['results'] ?? [];
            if (count($data) === 1 && $data[0]['id'] === 0) {
                $data = [];
            }
            return ServiceResponse::success(
                data: $data
            );
        } else {
            if (!isset($data['message'])) {
                Logger::error(
                    message: 'Error validating order data',
                    extra: [
                        "type" => $type,
                        "responseData" => $data
                    ]
                );
                throw new ServiceException(
                    message: 'Internal server error',
                    data: $data,
                    code: $response->status()
                );
            } else {
                return ServiceResponse::badRequest(
                    message: $data['message']
                );
            }
        }
    }

    public static function upgradeToAgent(
        User $user,
        string $agentToken,
        ?string $referredAgentCode
    ): ServiceResponse {
        $url = config('vars.tdms_api_url') . "/upgradeToAgent";

        $params = [
            'lastName' => $user->last_name,
            'firstName' => $user->first_name,
            'emailAddress' => $user->email,
            'currencyId' => self::CURRENCY_ID_AUD,
            'subSystemType' => self::SUB_SYSTEM_TYPE_CASHBACK,
            'token' => self::tokenFromUser($user),
        ];

        if ($referredAgentCode && $referredAgentCode !== config(key: 'vars.default_agent_branch_code')) {
            $params['referredBranch'] = $referredAgentCode;
        }

        $response = Http::asJson()
            ->timeout(120)
            ->withToken($agentToken)
            ->post($url, $params);

        if (!$response->successful()) {
            Logger::error(
                message: 'Error upgrading User to Agent',
                extra: array_merge($params, ["responseData" => $response->json()])
            );

            throw new ServiceException(
                message: 'Error upgrading User to Agent',
                data: $response->json(),
                code: $response->status()
            );
        }

        $responseData = $response->json();

        if (count($responseData['error'] ?? []) > 0) {
            $messages = array_map(fn ($item) => $item['message'], $responseData['error'] ?? []);

            return ServiceResponse::badRequest(
                message: implode(". ", $messages),
                data: $responseData,
            );
        }

        return ServiceResponse::success($responseData);
    }

    public static function upgradeToCommissionAgent(
        UserAgent $agent,
    ): ServiceResponse {
        $url = config('vars.tdms_api_url') . "/convertToFTA";

        $response = Http::asJson()
            ->timeout(120)
            ->withToken($agent->access_token)
            ->post($url);

        if (!$response->successful()) {
            Logger::error(
                message: 'Error upgrading User to Agent',
                extra: [
                    "agentBranch" => $agent->branch_code,
                    "responseData" => $response->json()
                ]
            );

            throw new ServiceException(
                message: 'Error upgrading User to Agent',
                data: $response->json(),
                code: $response->status()
            );
        }

        $responseData = $response->json();

        if (count($responseData['error'] ?? []) > 0) {
            $messages = array_map(fn ($item) => $item['message'], $responseData['error'] ?? []);

            return ServiceResponse::badRequest(
                message: implode(". ", $messages),
                data: $responseData,
            );
        }

        return ServiceResponse::success($responseData);
    }

    public static function updateAgentDetails(
        UserAgent $agent
    ): ServiceResponse {
        $url = config('vars.tdms_api_url') . "/updateAgentAccount";
        $body = [
            'bankBsb' => $agent->bank_bsb,
            'bankAccount' => $agent->bank_account ?? '',
            'bankCountryShortCode' => $agent->bank_country_short_code ?? '',
            'businessnumber' => $agent->business_number ?? '',
            'tradingname' => $agent->trading_name ?? '',
        ];

        $response = Http::asJson()
            ->withToken($agent->access_token)
            ->post($url, $body);

        if (!$response->successful()) {
            Logger::error(
                message: 'Error updating agent details',
                extra: [
                    "agentBranch" => $agent->branch_code,
                    "requestParams" => $body,
                    "responseData" => $response->json()
                ]
            );

            throw new ServiceException(
                message: 'Error upgrading User to Agent',
                data: $response->json(),
                code: $response->status()
            );
        }

        return ServiceResponse::success();
    }

    public static function getCommissionReport(
        string $agentToken,
        Carbon $startDate,
        Carbon $endDate,
        bool $onlyAvailablePoints = true,
        bool $untilTodayOnly = false
    ): ServiceResponse {
        $url = config('vars.tdms_api_url') . "/report/commissionReport";

        $params = [
            'onlyAvailable' => true,
            'upTodayDateOnly' => false,
        ];

        $response = Http::asJson()
            ->withToken($agentToken)
            ->post($url, $params);

        if (!$response->successful()) {
            Logger::error(
                message: 'Error fetching commission report',
                extra: array_merge($params, ["responseData" => $response->json()])
            );

            throw new ServiceException(
                message: 'Error fetching commission report - ' . $agentToken,
                data: $response->json(),
                code: $response->status()
            );
        }

        $responseData = $response->json();

        if (count($responseData['error'] ?? []) > 0) {
            $messages = array_map(fn ($item) => $item['message'], $responseData['error'] ?? []);

            return ServiceResponse::badRequest(
                message: implode(". ", $messages),
                data: $responseData,
            );
        }

        return ServiceResponse::success($responseData);
    }

    private static function tokenFromUser(User $user): string
    {
        return 'ft_'
            . $user->first_name
            . '@'
            . self::CURRENCY_ID_AUD
            . now()->format('Ym')
        ;
    }

    public static function getReferralSourcesOfAgent($accessToken, $getCached = true)
    {
        $url = config('vars.tdms_api_url') . 'apiv1/referralsources';
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$accessToken}"
        ])->get($url);

        if (!$response->successful()) {
            return ServiceResponse::badRequest(data: $response->json());
        }

        return ServiceResponse::success($response->json());
    }
}
