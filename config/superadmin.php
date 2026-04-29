<?php

return [
    'email' => env('SUPER_ADMIN_EMAIL'),
    'password_hash' => env('SUPER_ADMIN_PASSWORD_HASH'),
    'totp_secret' => env('SUPER_ADMIN_TOTP_SECRET'),
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('SUPER_ADMIN_ALLOWED_IPS', '127.0.0.1,::1'))
    ))),
];
