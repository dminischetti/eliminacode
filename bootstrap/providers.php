<?php

use App\Providers\AppServiceProvider;
use App\Providers\QueueRateLimiterProvider;

return [
    AppServiceProvider::class,
    QueueRateLimiterProvider::class,
];
