<?php

return [
    // §7
    'timezone' => 'Europe/Rome',

    // §31/§39
    'customer_poll_seconds' => (int) env('CUSTOMER_POLL_SECONDS', 5),
    'staff_poll_seconds' => (int) env('STAFF_POLL_SECONDS', 2),

    // §50 - Fase 1 gira interamente con false.
    'whatsapp_enabled' => (bool) env('WHATSAPP_ENABLED', false),

    // §58 - da tarare nel pilot: 3, poi eventualmente 4 o 5.
    'alert_ahead' => (int) env('ALERT_AHEAD', 3),

    // §66-§67 - Il timeout HTTP verso Meta deve restare ben sotto il lease,
    // altrimenti il recovery riarma la riga mentre la chiamata e' ancora in volo.
    'outbox_processing_timeout_seconds' => (int) env('OUTBOX_PROCESSING_TIMEOUT_SECONDS', 120),
    'outbox_max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 2),
    'whatsapp_http_timeout_seconds' => (int) env('WHATSAPP_HTTP_TIMEOUT_SECONDS', 20),

    // §57
    'whatsapp_data_retention_days' => (int) env('WHATSAPP_DATA_RETENTION_DAYS', 1),
];
