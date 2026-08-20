<?php

namespace App\Providers;

use App\Http\Middleware\AttachClientId;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Da registrare:
 *   Laravel 11+  ->  bootstrap/providers.php
 *   Laravel 10   ->  config/app.php, array 'providers'
 */
class QueueRateLimiterProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('tickets', function (Request $request) {
            $client = $request->cookie(AttachClientId::COOKIE) ?: $request->ip();

            return [
                // Un singolo browser non puo' martellare l'endpoint. Il limite
                // e' abbastanza alto da lasciar passare i retry con backoff di
                // una richiesta andata in timeout.
                Limit::perMinute(10)->by('coda:client:'.$client),

                // Tetto complessivo: blocca centinaia di richieste in pochi
                // secondi, ma un sabato mattina reale non ci arriva nemmeno
                // vicino.
                Limit::perMinute(120)->by('coda:shop'),
            ];
        });

        RateLimiter::for('whatsapp-links', function (Request $request) {
            $client = $request->cookie(AttachClientId::COOKIE) ?: $request->ip();

            return [
                Limit::perMinute(10)->by('coda:whatsapp-client:'.$client),
                Limit::perMinute(120)->by('coda:whatsapp-shop'),
            ];
        });
    }
}
