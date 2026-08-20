<?php

return [
    'enabled' => filter_var(env('WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOL),

    // Numero pubblico nel formato internazionale, solo cifre (es. 393331234567).
    'business_number' => preg_replace('/\D+/', '', (string) env('WHATSAPP_BUSINESS_NUMBER', '')),

    // Credenziali server-side della WhatsApp Cloud API.
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v25.0'),

    'warning_threshold' => max(1, (int) env('QUEUE_WARNING_THRESHOLD', 2)),
    'association_minutes' => max(5, (int) env('WHATSAPP_ASSOCIATION_MINUTES', 120)),
    'identifier_retention_days' => max(1, (int) env('WHATSAPP_RETENTION_DAYS', 2)),
    'webhook_retention_days' => max(7, (int) env('WHATSAPP_WEBHOOK_RETENTION_DAYS', 30)),
    'processing_timeout_seconds' => max(30, (int) env('WHATSAPP_PROCESSING_TIMEOUT_SECONDS', 120)),
    'http_timeout_seconds' => max(3, (int) env('WHATSAPP_HTTP_TIMEOUT_SECONDS', 10)),
    'http_connect_timeout_seconds' => max(1, (int) env('WHATSAPP_HTTP_CONNECT_TIMEOUT_SECONDS', 3)),
    'queue' => env('WHATSAPP_QUEUE', 'whatsapp'),
];
