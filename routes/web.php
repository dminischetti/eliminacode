<?php

use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\StaffQueueController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\QueuePageController;
use App\Http\Controllers\StaffPageController;
use App\Http\Controllers\StaffSessionController;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | Cliente
 |--------------------------------------------------------------------------
 | Il middleware AttachClientId e' registrato nello stack web, prima del
 | throttle della rotta di emissione.
 */
Route::get('/', [QueuePageController::class, 'show'])->name('queue.page');

Route::get('/api/queue', [QueueController::class, 'show'])->name('queue.state');

Route::post('/api/tickets', [TicketController::class, 'store'])
    ->middleware('throttle:tickets')
    ->name('tickets.store');

Route::get('/api/tickets/{token}/status', [TicketController::class, 'status'])
    ->name('tickets.status');

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

/*
 * IMPORTANTE - escludere POST api/tickets dal CSRF.
 *
 * La pagina cliente puo' restare aperta per ore: con il token CSRF legato
 * alla sessione, un cliente che prende il numero dopo molto tempo si
 * beccherebbe un 419 al posto del suo turno. L'endpoint e' gia' protetto
 * da idempotency key e rate limiting, e non c'e' nulla di sensibile da
 * forgiare. Le rotte staff restano invece protette dal CSRF: hanno una
 * sessione autenticata e il token viaggia nel meta tag della dashboard.
 *
 * Laravel 11+ in bootstrap/app.php:
 *     ->withMiddleware(function (Middleware $middleware) {
 *         $middleware->validateCsrfTokens(except: ['api/tickets']);
 *     })
 *
 * Laravel 10 in app/Http/Middleware/VerifyCsrfToken.php:
 *     protected $except = ['api/tickets'];
 */
