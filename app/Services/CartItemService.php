<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Events\OrderPosted;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\CartCustomerDetail;
use App\Models\Product;
use App\Models\ProductPriceAvailability;
use App\Models\UserOrder;
use Exception;

class CartItemService
{
    /**
     * @param string $userId
     * @param int $tdmsProductId
     * @param int $productPricesDetailsId
     * @param string $timeId
     * @param string $startDate
     * @param int $days
     * @param int[] $selectedAvailableIndices Array of integers
     */
    public static function saveItems(
        string $userId,
        int $tdmsProductId,
        int $productPricesDetailsId,
        string $timeId,
        string $startDate,
        int $days,
        array $selectedAvailableIndices,
    ) {
        try {
            // Validate that all indices are natural numbers
            foreach ($selectedAvailableIndices as $selectedIndex) {
                if (!is_int($selectedIndex) || $selectedIndex < 0) {
                    return ServiceResponse::badRequest(
                        message: 'Selected availability indices must be natural numbers',
                    );
                }
            }

            $userAgent = UserAgentService::getUserProfileAgent($userId);
            $defaultAgentAccessToken = $userAgent->access_token;
            $now = Carbon::now();
            $productDetailsResponse = ProductService::getProductDetails($defaultAgentAccessToken, $tdmsProductId);
            if (!$productDetailsResponse) {
                return ServiceResponse::notFound(
                    message: 'Product not found',
                );
            }
            $product = $productDetailsResponse['results'][0];
            $productAvailabilities = ProductService::getProductAvailabilitiesFromApi(
                $defaultAgentAccessToken,
                $productPricesDetailsId,
                $timeId,
                $startDate,
                $days,
            );

            if (empty($productAvailabilities)) {
                return ServiceResponse::notFound(
                    message: 'Product availability not found',
                );
            }

            $maxIndex = max($selectedAvailableIndices);
            if ($maxIndex >= count($productAvailabilities)) {
                return ServiceResponse::badRequest(
                    message: 'Selected availability selectedIndex is out of bounds',
                );
            }

            $bookingDetailsResponse = ProductService::getBookingDetails(
                agentToken: $defaultAgentAccessToken,
                productPricesDetailsId: $productPricesDetailsId,
            );

            if ($bookingDetailsResponse->isError()) {
                if ($bookingDetailsResponse->statusCode != 404) {
                    return ServiceResponse::notFound('Booking details not found');
                }

                $errorMessage = 'Failed to load booking details';
                Logger::error("{$errorMessage}: {$bookingDetailsResponse->message}");
                throw new ServiceException(message: $errorMessage);
            }
            $bookingDetails = $bookingDetailsResponse->data;

            $productLastUpdate = ProductService::getProductsLastUpdateFromApi(
                $defaultAgentAccessToken,
                [$tdmsProductId],
            );
            if (!$productLastUpdate) {
                throw new ServiceException(
                    message: 'Product last update not found',
                );
            }

            $productLastUpdate = $productLastUpdate[$tdmsProductId];

            $cartItems = [];
            $cartItems = DB::transaction(function () use (
                $tdmsProductId,
                $product,
                $now,
                $productLastUpdate,
                $productAvailabilities,
                $userId,
                $productPricesDetailsId,
                $timeId,
                $startDate,
                $days,
                $selectedAvailableIndices,
                $cartItems,
                $bookingDetails,
            ) {
                $existingProductQ = Product::where('tdms_product_id', $tdmsProductId);
                if ($existingProductQ->exists()) {
                    $existingProduct = $existingProductQ->first();
                    $should_update = empty($existingProduct->tdms_productLastUpdate_date) ||
                        $now->isAfter($existingProduct->tdms_productLastUpdate_date);

                    if ($should_update) {
                        // TODO: move existing productDetailsResponse to a product_history table
                        $existingProduct->update([
                            'json' => $product,
                            'tdms_product_last_update_date' => $now,
                        ]);
                    }
                } else {
                    Product::create([
                        'tdms_product_id' => $tdmsProductId,
                        'json' => $product,
                        'tdms_product_last_update_date' => $now,
                    ]);
                }

                foreach ($selectedAvailableIndices as $selectedIndex) {
                    $availability = $productAvailabilities[$selectedIndex];
                    $new_cart_item = CartItem::create([
                        'user_id' => $userId,
                        'tdms_product_id' => $tdmsProductId,
                        'product_price_details_id' => $productPricesDetailsId,
                        'time_id' => $timeId,
                        'booking_date' => BaseService::stringToDate($startDate),
                        'start_date' => BaseService::stringToDate($startDate),
                        'days' => $days,
                        'selected_index' => $selectedIndex,
                        'availability' => $availability,
                        'availability_last_updated_at' => $now,
                        'booking_details' => $bookingDetails,
                    ]);
                    array_push($cartItems, $new_cart_item);
                }

                return $cartItems;
            });

            return ServiceResponse::success(data: $cartItems);
        } catch (ServiceException $e) {
            Logger::error('Failed to save cart items', $e);
            return $e->toServiceResponse();
        }
    }

    public static function getItemsInCart($userId)
    {
        $cartItems = CartItem::userCartItems($userId)->get();
        $productIds = $cartItems->pluck('tdms_product_id')->unique();
        $products = Product::whereIn('tdms_product_id', $productIds)->get()->keyBy('tdms_product_id');

        $cartItems->each(function ($cartItem) use ($products) {
            $cartItem->product = $products->get($cartItem->tdms_product_id);
        });
        return ServiceResponse::success(data: $cartItems);
    }

    public static function updateItemBookingData($userId, int $cartItemId, int $quantity, mixed $bookingData)
    {
        $cartItem = CartItem::where('user_id', $userId)
            ->where('id', $cartItemId)
            ->first();

        if (!$cartItem) {
            return ServiceResponse::notFound(message: 'Cart item not found');
        }
        $cartItem->booking_quantity = $quantity;
        $cartItem->booking_data = $bookingData;

        $cartItem->save();
        return ServiceResponse::success('Cart item updated successfully');
    }

    public static function removeItemFromCart($userId, $cartItemId)
    {
        $cartItem = CartItem::where('user_id', $userId)->where('id', $cartItemId)->first();

        if (!$cartItem) {
            return ServiceResponse::notFound(
                message: 'Cart item not found',
            );
        }

        $cartItem->delete();

        return ServiceResponse::success();
    }

    /**
     * Set customer details with customer_index validation and synchronization.
     *
     * @param string $userId
     * @param array $data
     */
    public static function setCustomers(string $userId, array $data)
    {
        // Validate customer_index sequence
        $indices = array_column($data, 'customerIndex');
        sort($indices);

        foreach ($indices as $key => $index) {
            if ($index !== $key) {
                return ServiceResponse::badRequest(
                    message: 'Invalid index',
                );
            }
        }

        DB::transaction(function () use ($userId, $data) {
            $existingDetails = CartCustomerDetail::where('user_id', $userId)->get()->keyBy('customer_index');

            $newIndices = array_column($data, 'customerIndex');

            // Update or create records
            foreach ($data as $detail) {
                CartCustomerDetail::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'customer_index' => $detail['customerIndex'],
                    ],
                    [
                        'user_id' => $userId,
                        'first_name' => $detail['firstName'],
                        'last_name' => $detail['lastName'],
                        'date_of_birth' => $detail['dateOfBirth'],
                        'email' => $detail['email'],
                        'postal_code' => $detail['postalCode'] ?? null,
                        'customer_index' => $detail['customerIndex'],
                        'phone_number' => $detail['phoneNumber']
                    ],
                );
            }

            // Delete records that are in the database but not in the input
            Logger::debug($existingDetails->keys());
            $indicesToDelete = $existingDetails->keys()->diff($newIndices);
            Logger::debug($indicesToDelete);
            if ($indicesToDelete->isNotEmpty()) {
                CartCustomerDetail::where('user_id', $userId)
                    ->whereIn('customer_index', $indicesToDelete)
                    ->delete();
            }
        });

        return ServiceResponse::success();
    }

    public static function getCustomers(string $userId)
    {
        $customerDetails = CartCustomerDetail::where('user_id', $userId)->get();
        return ServiceResponse::success(data: $customerDetails);
    }

    public static function getUserCartItemsWithProductDetails($userId)
    {
        $cartItems = CartItem::where('user_id', $userId)
                                ->select('id', 'tdms_product_id', 'product_price_details_id', 'booking_date', 'time_id')
                                ->get();

        $itemsWithProductDetails = [];
        if (!$cartItems) {
            return $itemsWithProductDetails;
        }

        foreach ($cartItems as $item) {
            $productPriceAvailability = ProductPriceAvailability::where('tdms_product_id', $item->tdms_product_id)
                                            ->where('product_price_details_id', $item->product_price_details_id)
                                            ->first();
            $item = $item->toArray();
            $tdmsProductId = $item['tdms_product_id'];

            $productDetailsResponse = Product::where('tdms_product_id', $tdmsProductId)
                            ->orderBy('version', 'desc')
                            ->first();
            if ($productDetailsResponse) {
                $item['version'] = $productDetailsResponse->version;
                $item['counter'] = $productDetailsResponse->counter;
                $item['details'] = $productDetailsResponse->json;
                $item['details']['availability'] = $productPriceAvailability
                                                ? $productPriceAvailability->toArray()
                                                : [];
            } else {
                $item['version'] = 0;
                $item['counter'] = 0;
                $item['details'] = null;
            }
            $itemsWithProductDetails[] = $item;
        }

        return $itemsWithProductDetails;
    }

    public static function getCartItemProductAvailability($agentToken, $item)
    {
        if (!$item) {
            return null;
        }

        $productPricesDetailsId = $item->product_price_details_id;
        $timeId = $item->time_id;
        $startDate = $item->booking_date;

        $requestUrl = config('vars.tdms_api_url');
        $curl = curl_init();
        curl_setopt(
            $curl,
            CURLOPT_URL,
            "{$requestUrl}/checkavailabilityrange/{$productPricesDetailsId}/{$timeId}/{$startDate}/1"
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentToken}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if (!$result || isset($result['errors'])) {
            return null;
        }
        return $result[0];
    }

    public static function getCartItemBookingDetails($agentToken, $item)
    {
        if (!$item) {
            return null;
        }

        $productPricesDetailsId = $item->product_price_details_id;


        $requestUrl = config('vars.tdms_api_url');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/bookingdetails/{$productPricesDetailsId}");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentToken}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if (isset($result['errors'])) {
            return null;
        }
        return $result;
    }

    public static function cleanCartItems(
        string $userId,
        $bookingReference,
        $cartItemIds,
        $requestData,
        $responseData,
        $intent,
    ) {
        DB::transaction(function () use (
            $bookingReference,
            $cartItemIds,
            $responseData,
            $requestData,
            $intent,
            $userId,
        ) {
            //TODO: remove customers
            $userOrder = UserOrder::create([
                'booking_reference' => $bookingReference,
                'cart_item_ids' => $cartItemIds,
                'intent' => $intent,
                'user_id' => $userId,
                'request_data' => $requestData,
                'response_data' => $responseData,
            ]);

            if ($intent === 'email-quote') { // emailing quote should remove items from cart
                CartItem::whereIn('id', $cartItemIds)->update(['user_order_id' => $userOrder->id]);
            }
        });
    }
}
