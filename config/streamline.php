<?php

return [
    'class_namespace' => 'App\\Streams',
    'class_postfix' => 'Stream',
    'route' => 'api/streamline',
    'flat_route' => 'api/streamline',

    // Middleware every non-guest stream request is sent through, per request.
    'middleware' => ['auth:sanctum'],

    // Streams reachable without auth, matched on the slug ...
    'guest_streams' => [
        'auth/auth',
    ],

    // ... or on the resolved Stream class (FQCN).
    'guest_classes' => [],
];
