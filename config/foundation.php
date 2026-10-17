<?php

/*
|--------------------------------------------------------------------------
| Foundation
|--------------------------------------------------------------------------
|
| Settings of the Foundation module (app/Modules/Foundation): the
| cross-cutting services every module relies on.
|
*/

return [

    'security' => [

        /*
         * Headers added to every response by the AddSecurityHeaders middleware.
         * A header set to null is not sent.
         */
        'headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ],

        /*
         * Strict-Transport-Security is only sent over HTTPS. Keep it disabled
         * until every subdomain is served over HTTPS.
         */
        'hsts' => [
            'enabled' => (bool) env('SECURITY_HSTS', false),
            'max_age' => 31536000,
        ],
    ],

    'api' => [

        /*
         * API tokens stop working after this many days; apps sign in again.
         */
        'token_expiration_days' => (int) env('API_TOKEN_EXPIRATION_DAYS', 30),

        /*
         * Requests per minute for each signed-in user (or IP address).
         */
        'rate_limit' => (int) env('API_RATE_LIMIT', 60),
    ],

    'login_history' => [

        /*
         * Sign-in records older than this are pruned by `model:prune`.
         */
        'retention_days' => (int) env('LOGIN_HISTORY_RETENTION_DAYS', 180),
    ],

    'exports' => [

        /*
         * PDF files are rendered in memory, so they are limited to this many
         * rows. Spreadsheet exports have no limit.
         */
        'pdf_max_rows' => 2000,
    ],

    'imports' => [

        /*
         * The largest spreadsheet accepted by an import, in kilobytes.
         */
        'max_file_size' => 5120,
    ],

];
