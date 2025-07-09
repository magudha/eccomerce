<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | This option defines the default currency for payments when none is
    | specified. This should be a valid 3-letter currency code.
    |
    */
    'default_currency' => env('PAYMENT_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Enabled Payment Gateways
    |--------------------------------------------------------------------------
    |
    | This array contains the list of payment gateways that are enabled
    | for your application. You can disable gateways by removing them
    | from this array.
    |
    */
    'enabled_gateways' => [
        'stripe',
        'paypal',
        'razorpay',
        'flutterwave',
        'paystack',
        'mpesa',
        'paytm',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configurations
    |--------------------------------------------------------------------------
    |
    | Here you can configure the settings for each payment gateway.
    | Each gateway should have its own configuration array.
    |
    */
    'gateways' => [
        'stripe' => [
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'currency' => env('STRIPE_CURRENCY', 'USD'),
            'api_version' => env('STRIPE_API_VERSION', '2022-11-15'),
        ],

        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
            'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
            'currency' => env('PAYPAL_CURRENCY', 'USD'),
        ],

        'razorpay' => [
            'key_id' => env('RAZORPAY_KEY_ID'),
            'key_secret' => env('RAZORPAY_KEY_SECRET'),
            'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
            'currency' => env('RAZORPAY_CURRENCY', 'INR'),
        ],

        'flutterwave' => [
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'hash' => env('FLUTTERWAVE_HASH'),
            'environment' => env('FLUTTERWAVE_ENV', 'staging'), // staging or live
            'currency' => env('FLUTTERWAVE_CURRENCY', 'USD'),
        ],

        'paystack' => [
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),
            'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
        ],

        'mpesa' => [
            'consumer_key' => env('MPESA_CONSUMER_KEY'),
            'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
            'business_short_code' => env('MPESA_BUSINESS_SHORT_CODE'),
            'passkey' => env('MPESA_PASSKEY'),
            'environment' => env('MPESA_ENV', 'sandbox'), // sandbox or live
            'currency' => 'KES',
        ],

        'paytm' => [
            'merchant_id' => env('PAYTM_MERCHANT_ID'),
            'merchant_key' => env('PAYTM_MERCHANT_KEY'),
            'website' => env('PAYTM_WEBSITE', 'WEBSTAGING'),
            'industry_type' => env('PAYTM_INDUSTRY_TYPE', 'Retail'),
            'channel_id' => env('PAYTM_CHANNEL_ID', 'WEB'),
            'environment' => env('PAYTM_ENV', 'staging'), // staging or production
            'currency' => env('PAYTM_CURRENCY', 'INR'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Limits
    |--------------------------------------------------------------------------
    |
    | Configure minimum and maximum payment amounts for security.
    |
    */
    'limits' => [
        'min_amount' => env('PAYMENT_MIN_AMOUNT', 0.01),
        'max_amount' => env('PAYMENT_MAX_AMOUNT', 999999.99),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry attempts for failed payments and API calls.
    |
    */
    'retry' => [
        'max_attempts' => env('PAYMENT_RETRY_ATTEMPTS', 3),
        'delay_seconds' => env('PAYMENT_RETRY_DELAY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration for payments.
    |
    */
    'security' => [
        'encrypt_credentials' => env('PAYMENT_ENCRYPT_CREDENTIALS', true),
        'log_sensitive_data' => env('PAYMENT_LOG_SENSITIVE_DATA', false),
        'require_https' => env('PAYMENT_REQUIRE_HTTPS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook handling.
    |
    */
    'webhooks' => [
        'verify_signatures' => env('PAYMENT_VERIFY_WEBHOOK_SIGNATURES', true),
        'timeout_seconds' => env('PAYMENT_WEBHOOK_TIMEOUT', 30),
        'retry_failed_webhooks' => env('PAYMENT_RETRY_FAILED_WEBHOOKS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    |
    | Database-related configuration for payments.
    |
    */
    'database' => [
        'payment_transactions_table' => 'payment_transactions',
        'billing_methods_table' => 'billing_methods',
        'use_soft_deletes' => true,
        'cleanup_after_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure notifications for payment events.
    |
    */
    'notifications' => [
        'payment_success' => [
            'enabled' => env('NOTIFY_PAYMENT_SUCCESS', true),
            'channels' => ['mail', 'database'],
        ],
        'payment_failed' => [
            'enabled' => env('NOTIFY_PAYMENT_FAILED', true),
            'channels' => ['mail', 'database'],
        ],
        'refund_processed' => [
            'enabled' => env('NOTIFY_REFUND_PROCESSED', true),
            'channels' => ['mail', 'database'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | List of currencies supported by the application.
    |
    */
    'supported_currencies' => [
        'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY', 'INR', 'SGD',
        'KES', 'NGN', 'GHS', 'ZAR', 'EGP', 'MAD', 'TND',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Configuration
    |--------------------------------------------------------------------------
    |
    | Configure payment processing fees.
    |
    */
    'fees' => [
        'enabled' => env('PAYMENT_FEES_ENABLED', false),
        'default_percentage' => env('PAYMENT_DEFAULT_FEE_PERCENTAGE', 2.9),
        'default_fixed' => env('PAYMENT_DEFAULT_FEE_FIXED', 0.30),
        'gateway_specific' => [
            'stripe' => ['percentage' => 2.9, 'fixed' => 0.30],
            'paypal' => ['percentage' => 3.4, 'fixed' => 0.30],
            'razorpay' => ['percentage' => 2.0, 'fixed' => 0.00],
        ],
    ],
];