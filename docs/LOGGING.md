# Activity Logging Documentation

This document describes the logging system implementation for tracking user activities and system events.

## Overview

The logging system writes activity logs to CSV files organized by date in `storage/logs/{YYYY-MM-DD}/`. Each log type has its own CSV file for easy filtering and analysis.

### Log Levels

- **Debug**: Always written to CSV file when `log_file` key is provided in the data array
- **Error/Exception**: Written to CSV only when `LOG_WRITE_ERRORS_TO_FILE=true` in `.env`, always reported to Retack

## Log Files and Activities

### User Related

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `verify-account.csv` | `account_verification` | Account verification emails | `SendProfileEmailOtp.php` |
| `forgot-password.csv` | `forgot_password` | Password reset requests | `SendForgotPasswordOtp.php` |
| `fcm-subscription.csv` | `fcm_subscription` | FCM topic subscribe/unsubscribe, access token | `FcmService.php`, `SubscribeToFCMTopic.php`, `UnsubscribeFromFCMTopic.php` |
| `user-activity.csv` | `user_activity` | Login, signup, logout, account deletion | `UserController.php`, `GoogleLoginController.php`, `AppleLoginController.php`, `DeleteAccountPermanentlyJob.php` |
| `user-auth.csv` | `user_auth` | Authentication events | `UserController.php`, `GoogleService.php`, `AppleService.php` |
| `user-otp.csv` | `user_otp` | OTP generation and verification | `OtpService.php`, `OtpController.php` |
| `user-jwt.csv` | `user_jwt` | JWT token operations | `JwtService.php`, `JwtAuthenticate.php` |
| `user-profile.csv` | `user_profile` | Profile updates, FCM token management, redeemer operations | `UserService.php`, `RedeemerService.php`, `RedeemerController.php` |

### Cart and Quote

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `cart.csv` | `cart` | Add/remove/update cart items, cart cleanup | `CartItemService.php`, `CartItemController.php`, `CleanCartItems.php` |
| `quote.csv` | `quote` | Quote creation, conversion, email | `CartItemService.php`, `EmailQuote.php`, `ShareQuoteJob.php` |

### Order and Booking

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `order.csv` | `order` | Order submission, completion | `BookingService.php`, `CartItemController.php`, `EmailCompleteOrder.php`, `SendOrderCompleteEmail.php` |
| `booking.csv` | `booking` | Booking data, notifications | `BookingService.php`, `SetBookingNotificationData.php`, `SendBookingNotification.php` |
| `payment.csv` | `payment` | Payment processing, payment links | `BookingService.php`, `SharePaymentLinkJob.php` |

### External API

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `tdms.csv` | `tdms` | TDMS API calls (agent token, booking, orders) | `TdmsService.php` |

### Products

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `products.csv` | `products` | Product details, availability, booking details, cache operations, home feed | `ProductService.php`, `ProductController.php`, `ProductCategoryService.php`, `ProductCacheService.php`, `CountryLocationsJob.php`, `HomeFeedCachedProductUpdate.php`, `HomeFeedProductByCategories.php` |

### Agent

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `agent.csv` | `agent` | Agent CRUD, token refresh, referral sources | `UserAgentService.php`, `UserAgentController.php`, `AgentTokenService.php` |

### Email

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `email.csv` | `email` | Email sending via Brevo | `BrevoEmailService.php`, `SendShareMailJob.php` |

### Cache

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `cache.csv` | `cache` | Redis cache write/delete operations | `UserCacheService.php` |

### Discount/Commission

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `discount.csv` | `discount` | Discount CRUD, commission calculations | `DiscountService.php`, `DiscountController.php`, `UserOrderCommissionService.php` |

### Notifications

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `notifications.csv` | `notifications` | FCM notifications, points earned, inactive user notifications | `FcmService.php`, `PointsEarnedNotification.php`, `SendPointsEarnedNotificationJob.php`, `SendNotificationToTopic.php`, `SendNotificationToInactiveUsers.php` |

### Errors

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `errors.csv` | `errors` | General errors (when write_errors_to_file is enabled) | Various error handlers |

### Favourites

| Log File | Config Key | Activities Logged | Files Using It |
|----------|------------|-------------------|----------------|
| `favourites.csv` | `favourites` | Favourite products add/remove/list | `FavouritesController.php`, `FavouritesService.php` |

---

## Usage Example

```php
use App\Logging\Logger;

// Debug level - always writes to file when log_file is provided
Logger::debug('User logged in', [
    'log_file' => config('logging.log_files.user_activity'),
    'user_id' => $user->uuid,
    'user_email' => $user->email,
    'action' => 'user_login',
    'platform' => $platform,
]);

// Error level - writes to file only if LOG_WRITE_ERRORS_TO_FILE=true
Logger::error('Failed to process order', $exception, data: [
    'log_file' => config('logging.log_files.order'),
    'order_id' => $orderId,
    'action' => 'order_processing_failed',
]);

// Exception level - writes to file only if LOG_WRITE_ERRORS_TO_FILE=true
Logger::exception('Critical error occurred', data: [
    'log_file' => config('logging.log_files.errors'),
    'action' => 'critical_error',
], exception: $exception);
```

## Configuration

In `.env`:

```env
# Enable writing error/exception logs to CSV files
LOG_WRITE_ERRORS_TO_FILE=true
```

## File Location

All CSV log files are stored in:
```
storage/logs/{YYYY-MM-DD}/{log-file-name}.csv
```

Example:
```
storage/logs/2025-12-19/user-activity.csv
storage/logs/2025-12-19/cart.csv
storage/logs/2025-12-19/order.csv
```
