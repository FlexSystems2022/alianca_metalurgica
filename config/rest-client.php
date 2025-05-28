<?php

return [
    /**
     * Rest client environment for selecting services
     * Available: 'production', 'dev'
     */
    'environment' => env('REST_CLIENT_ENV', 'dev'),

    /**
     * Debug mode for showing logs
     */
    'debug_mode' => true,

    /**
     * Access Token cache time
     * Set 0 to disable cache of access tokens
     */
    'oauth_tokens_cache_minutes' => 0,

    /**
     *  Guzzle Client Config
     */
    'guzzle_client_config' => [
        'timeout' => 600.0,      // Request timeout: 29 secs
        'http_errors' => false,
    ],

    
    /**
     * Shared config for services
     */
    'shared_service_config' => [

        'headers' => [
            'User-Agent' => 'someline-testing/1.0',
            'Authorization' => 'Basic '.base64_encode(env("OAUTH2_CLIENT_ID").":".env("OAUTH2_CLIENT_SECRET"))
        ],

        'api_url' => 'https://api.nexti.com/',

        'oauth2_credentials' => [
            'client_id' => env("OAUTH2_CLIENT_ID"),
            'client_secret' => env("OAUTH2_CLIENT_SECRET"),
        ],

        'oauth2_access_token_url' => 'security/oauth/token',

        'oauth2_grant_types' => [
            'client_credentials' => 'client_credentials',
            'authorization_code' => 'authorization_code',
            'refresh_token' => 'refresh_token',
            'password' => 'password',
        ],

    ],

    /**
     * Default Service
     */
    'default_service_name' => 'nexti',

    /**
     * Services
     */
    'services' => [
        'dev' => [
            'nexti' => [
                'base_uri' => 'https://api.nexti.com/',
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ],
            'someline-starter' => [
                'base_uri' => 'https://api.nexti.com/',

                'headers' => [
                    'Accept' => 'application/json',
                ],
            ],
        ],

        // environment: production
        'production' => [
            'nexti' => [
                'base_uri' => 'https://api.nexti.com/',

                'headers' => [
                    'Accept' => 'application/json',
                ],

            ],
        ],
    ],
];