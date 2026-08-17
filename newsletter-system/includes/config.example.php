<?php

return [
    'app_url' => 'https://example.com/newsletter',
    'timezone' => 'Asia/Muscat',
    'encryption_key' => 'change-this-to-a-long-random-secret',
    'database' => [
        'host' => 'localhost',
        'name' => 'newsletter_db',
        'user' => 'newsletter_user',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
    ],
    'uploads' => [
        'max_size_mb' => 10,
        'max_width' => 2400,
        'quality' => 82,
    ],
    'mail' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'hello@omqpro.com',
        'password' => '',
        'encryption' => 'tls',
        'batch_size' => 25,
        'batch_delay_seconds' => 60,
    ],
];
