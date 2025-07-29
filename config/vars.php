<?php

return [
    'queue_connection' => env('QUEUE_CONNECTION'),

    'brevo_mail_api_key' => env('BREVO_MAIL_API_KEY'),
    'mail_from_address' => env('MAIL_FROM_ADDRESS'),
    'notification_to_freelance_email_address' => env('NOTIFICATION_TO_FREELANCE_EMAIL_ADDRESS'),
    'verify_email_template_id' => env('BREVO_VERIFY_EMAIL_TEMPLATE_ID'),
    'forgot_password_template_id' => env('BREVO_FORGOT_PASSWORD_TEMPLATE_ID'),
    'booking_notification_template_id' => env('BREVO_BOOKING_NOTIFICATION_TEMPLATE_ID'),
    'order_complete_template_id' => env('BREVO_ORDER_COMPLETE_TEMPLATE_ID'),
    'account_deletion_template_id' => env('BREVO_ACCOUNT_DELETION_TEMPLATE_ID'),
    'points_earned_template_id' => env('BREVO_POINTS_EARNED_NOTIFICATION_TEMPLATE_ID'),

    'sso_type_email' => env('SSO_TYPE_EMAIL'),
    'sso_type_google' => env('SSO_TYPE_GOOGLE'),
    'sso_type_apple' => env('SSO_TYPE_APPLE'),

    'google_client_ids' => env('GOOGLE_CLIENT_IDS'),
    'apple_client_id' => env('APPLE_CLIENT_ID'),

    'jwt_token_encrypt_algorithm' => env('JWT_TOKEN_ENCRYPT_ALGORITHM'),
    'access_token_validity_period_in_minutes' => env('ACCESS_TOKEN_VALIDITY_PERIOD_IN_MINUTES'),
    'refresh_token_validity_period_in_minutes' => env('REFRESH_TOKEN_VALIDITY_PERIOD_IN_MINUTES'),

    'tdms_customer_api_username' => env('TDMS_CUSTOMER_API_USERNAME'),
    'tdms_customer_api_password' => env('TDMS_CUSTOMER_API_PASSWORD'),

    'tdms_api_url' => env('TDMS_API_URL'),
    'tdms_customer_api_url' => env('TDMS_CUSTOMER_API_URL'),

    'retack_error_logging_url' => env('RETACK_ERROR_LOGGING_URL'),
    'retack_env_key' => env('RETACK_ENV_KEY'),

    'default_agent_branch_code' => env('DEFAULT_AGENT_BRANCH_CODE'),
    'default_agent_email' => env('DEFAULT_AGENT_EMAIL'),
    'default_agent_password' => env('DEFAULT_AGENT_PASSWORD'),
    'default_agent_token_username' => env('DEFAULT_AGENT_EMAIL'),
    'default_agent_token_password' => env('DEFAULT_AGENT_PASSWORD'),

    'only_char_regex' => '/^[a-zA-Z]+$/',

    'discount_percentage' => (int) env('DISCOUNT_PERCENTAGE', 0),

    'agent_upgrade_order_months_history' => env('AGENT_UPGRADE_ORDER_MONTHS_HISTORY', 6),
    'referral_source_id_period_months' => env('REFERRAL_SOURCE_ID_PERIOD_MONTHS', 6),
    'points_multiplier' => env('POINTS_MULTIPLIER', 1000),
];
