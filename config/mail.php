<?php

return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'array' => [
            'transport' => 'array',
        ],
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
        'failover' => [
            'transport' => 'failover',
            'mailers' => ['log'],
        ],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'P4DC')),
    ],
];
