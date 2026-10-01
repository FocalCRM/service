<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Define the database table names used by the Focal Service package.
    |
    */
    'tables' => [
        'tickets' => 'focal_service_tickets',
        'messages' => 'focal_service_ticket_messages',
        'sla_policies' => 'focal_service_sla_policies',
        'articles' => 'focal_service_articles',
        'canned_responses' => 'focal_service_canned_responses',
        'routing_rules' => 'focal_service_routing_rules',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ticket Defaults
    |--------------------------------------------------------------------------
    |
    | Default configurations for new support tickets.
    |
    */
    'defaults' => [
        'priority' => 'medium',
        'source' => 'web_portal',
        'prefix' => 'TICK',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The help center and customer support portal are registered in the "web"
    | group. The inbound email webhook, knowledge deflection and chat widget
    | APIs are registered in the "api" group. Each group accepts a domain,
    | prefix and middleware. Set "enabled" to false to register your own
    | routes instead; keep the focal.help.*, focal.support.* and
    | focal.service.* route names, since notifications link to them.
    |
    */
    'routes' => [
        'enabled' => (bool) env('FOCAL_SERVICE_ROUTES_ENABLED', true),

        'web' => [
            'domain' => env('FOCAL_SERVICE_DOMAIN'),
            'prefix' => env('FOCAL_SERVICE_PREFIX', ''),
            'middleware' => ['web'],
        ],

        'api' => [
            'domain' => env('FOCAL_SERVICE_DOMAIN'),
            'prefix' => env('FOCAL_SERVICE_API_PREFIX', 'api/service'),
            'middleware' => ['web'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | Shared secret for this package's server-to-server endpoints (webhooks and
    | sending APIs). Send it as 'Authorization: Bearer <token>', an
    | 'X-Focal-Token' header, or a '?token=' query parameter. While empty, those
    | endpoints are disabled. Generate one with: php -r 'echo bin2hex(random_bytes(32));'
    |
    */
    'api' => [
        'token' => env('FOCAL_SERVICE_API_TOKEN'),
    ],
];
