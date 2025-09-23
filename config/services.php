<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY', 'pk_test_51Qy4chP7Wt2ShBcLOpxSb1WwTkydp5WmPJglymQ8lMUG9GuKaUiKvTPlVKoWQ30qV2ZcqKxOOOM2LUY1j8JuR3N500w0e4cmIb'),
        'secret' => env('STRIPE_SECRET', 'sk_test_51Qy4chP7Wt2ShBcLHWMOVGWhi3cTVdovTmFak8aihxhHNFxSCMWM2YswYplZJdkKWL3s4hC0OON07bsBOvc9r0ds00rGPCvx3s'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
