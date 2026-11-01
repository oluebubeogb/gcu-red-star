<?php
return [
    'admin_usernames' => env('ADMIN_USERNAMES', ''),
    'magazine_disk' => env('MAGAZINE_DISK', 'magazines'),
    'institution' => env('REDSTAR_INSTITUTION', 'Government College Umuahia'),
    'tagline' => env('REDSTAR_TAGLINE', 'The Voice of Government College Umuahia'),
    'seed_admin' => [
        'email' => env('SEED_ADMIN_EMAIL'),
        'username' => env('SEED_ADMIN_USERNAME', 'admin'),
        'name' => env('SEED_ADMIN_NAME', 'Red Star Admin'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],
];
