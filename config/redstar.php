<?php
return [
    'admin_usernames' => env('ADMIN_USERNAMES', ''),
    'magazine_disk' => env('MAGAZINE_DISK', 'magazines'),
    // Redis list the Python worker BLPOPs (Laravel's REDIS_PREFIX is prepended). Empty = DB poll only.
    'pdf_queue' => env('REDSTAR_PDF_QUEUE', 'redstar:pdf'),
    // Demo data tools in HQ are always on outside production; in production only when this is true.
    'allow_demo' => (bool) env('REDSTAR_ALLOW_DEMO', false),
    'max_pdf_kb' => (int) env('REDSTAR_MAX_PDF_KB', 102400),
    'institution' => env('REDSTAR_INSTITUTION', 'Government College Umuahia'),
    'tagline' => env('REDSTAR_TAGLINE', 'The Voice of Government College Umuahia'),
    'seed_admin' => [
        'email' => env('SEED_ADMIN_EMAIL'),
        'username' => env('SEED_ADMIN_USERNAME', 'admin'),
        'name' => env('SEED_ADMIN_NAME', 'Red Star Admin'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],
];
