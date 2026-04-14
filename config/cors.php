<?php

return [

    /*
     * Which routes CORS headers are applied to.
     * 'api/*' covers every endpoint in routes/api.php.
     */
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    /*
     * Set FRONTEND_URL in your production environment to the exact Vercel URL,
     * e.g. https://devboard.vercel.app
     * Multiple origins can be separated by commas.
     */
    'allowed_origins' => array_filter(
        array_map(
            fn(string $o) => rtrim(trim($o), '/'),
            explode(',', env('FRONTEND_URL', 'http://localhost:3000'))
        )
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
