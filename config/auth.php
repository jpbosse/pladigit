<?php
return [
    'defaults' => [
        'guard'     => 'web',   // Guard par défaut = tenant
        'passwords' => 'users',
    ],
 
    'guards' => [

        'web' => [
           'driver'   => 'session',
           'provider' => 'tenant_users',
        ],

        // Guard tenant — utilise la connexion 'tenant' (base dédiée)
        'tenant' => [
            'driver'   => 'session',
            'provider' => 'tenant_users',
        ],
 
        // Guard super-admin — utilise les credentials .env
        'super-admin' => [
            'driver'   => 'session',
            'provider' => 'tenant_users',
        ],
 
        'api' => [
            'driver'   => 'sanctum',
            'provider' => 'tenant_users',
        ],
    ],
 
    'providers' => [
        // Provider tenant — modèle User de la base tenant
        'tenant_users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Tenant\User::class,
        ],
	'super_admin_users' => [
	    'driver' => 'array',
	],
    ],
 
    'passwords' => [
        'users' => [
            'provider' => 'tenant_users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],
 
    'password_timeout' => 10800,
];
