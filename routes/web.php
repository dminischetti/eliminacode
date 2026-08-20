<?php

use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\StaffQueueController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketWhatsAppController;
use App\Http\Controllers\QueuePageController;
use App\Http\Controllers\StaffPageController;
use App\Http\Controllers\StaffSessionController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

$statelessCustomerMiddleware = [
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
];

/*
 |--------------------------------------------------------------------------
 | Cliente
 |--------------------------------------------------------------------------
 | Il middleware AttachClientId e' registrato nello stack web, prima del
 | throttle della rotta di emissione.
 */
Route::get('/', [QueuePageController::class, 'show'])
    ->withoutMiddleware($statelessCustomerMiddleware)
    ->name('queue.page');

Route::get('/api/queue', [QueueController::class, 'show'])
    ->withoutMiddleware($statelessCustomerMiddleware)
    ->name('queue.state');

Route::post('/api/tickets', [TicketController::class, 'store'])
    ->middleware('throttle:tickets')
    ->withoutMiddleware($statelessCustomerMiddleware)
    ->name('tickets.store');

Route::get('/api/tickets/{token}/status', [TicketController::class, 'status'])
    ->withoutMiddleware($statelessCustomerMiddleware)
    ->name('tickets.status');

Route::post('/api/tickets/{token}/whatsapp-link', [TicketWhatsAppController::class, 'store'])
    ->middleware('throttle:whatsapp-links')
    ->withoutMiddleware($statelessCustomerMiddleware)
    ->name('tickets.whatsapp-link');

/* Meta verifica il callback con GET e consegna messaggi/stati con POST. */
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->withoutMiddleware($statelessCustomerMiddleware)
    ->name('whatsapp.webhook.verify');

Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->withoutMiddleware($statelessCustomerMiddleware)
    ->name('whatsapp.webhook.receive');

/*
 |--------------------------------------------------------------------------
 | Accesso staff
 |--------------------------------------------------------------------------
 | La rotta di login si chiama "login" perche' e' il nome su cui il
 | middleware auth di Laravel reindirizza da solo.
 */
Route::get('/staff/login', [StaffSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/staff/login', [StaffSessionController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1']);

Route::post('/staff/logout', [StaffSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('staff.logout');

/*
 |--------------------------------------------------------------------------
 | Area staff
 |--------------------------------------------------------------------------
 */
Route::middleware('auth')->group(function () {
    Route::get('/staff', [StaffPageController::class, 'show'])->name('staff.dashboard');

    Route::get('/api/staff/queue', [StaffQueueController::class, 'show']);
    Route::post('/api/staff/queue/next', [StaffQueueController::class, 'next']);
    Route::post('/api/staff/queue/correct', [StaffQueueController::class, 'correct']);
    Route::post('/api/staff/queue/close', [StaffQueueController::class, 'close']);
    Route::post('/api/staff/queue/reopen', [StaffQueueController::class, 'reopen']);
});
