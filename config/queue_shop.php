<?php

return [
    // §7
    'timezone' => 'Europe/Rome',

    // §31/§39
    'customer_poll_seconds' => (int) env('CUSTOMER_POLL_SECONDS', 5),
    'staff_poll_seconds' => (int) env('STAFF_POLL_SECONDS', 2),

    'location' => env('QUEUE_LOCATION', 'Rigopiano · Gran Sasso'),

];
