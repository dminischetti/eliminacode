<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckProductionReadiness extends Command
{
    protected $signature = 'coda:check {--production : Applica anche i controlli obbligatori per la produzione}';

    protected $description = 'Verifica configurazione, database e prerequisiti operativi';

    public function handle(): int
    {
        $checks = [
            'APP_KEY configurata' => fn (): bool => filled(config('app.key')),
            'Database raggiungibile' => fn (): bool => DB::connection()->getPdo() !== null,
            'Migrazioni applicate' => fn (): bool => collect([
                'users',
                'queue_days',
                'tickets',
                'queue_corrections',
                'whatsapp_messages',
                'whatsapp_webhook_events',
            ])
                ->every(fn (string $table): bool => Schema::hasTable($table)),
            'Account staff presente' => fn (): bool => Schema::hasTable('users') && User::query()->exists(),
            'storage scrivibile' => fn (): bool => is_writable(storage_path()),
            'bootstrap/cache scrivibile' => fn (): bool => is_writable(base_path('bootstrap/cache')),
        ];

        if ($this->option('production') || app()->environment('production')) {
            $checks += [
                'APP_ENV=production' => fn (): bool => app()->environment('production'),
                'APP_DEBUG=false' => fn (): bool => config('app.debug') === false,
                'APP_URL usa HTTPS' => fn (): bool => str_starts_with((string) config('app.url'), 'https://'),
                'Cookie di sessione sicuro' => fn (): bool => config('session.secure') === true,
            ];
        }

        if (config('database.default') === 'sqlite') {
            $checks += [
                'SQLite busy timeout configurato' => fn (): bool => (int) config('database.connections.sqlite.busy_timeout') > 0,
                'SQLite transaction mode IMMEDIATE' => fn (): bool => strtoupper((string) config('database.connections.sqlite.transaction_mode')) === 'IMMEDIATE',
            ];
        }

        if (config('whatsapp.enabled')) {
            $checks += [
                'WhatsApp numero business configurato' => fn (): bool => preg_match('/^[0-9]{5,20}$/', (string) config('whatsapp.business_number')) === 1,
                'WhatsApp Phone Number ID configurato' => fn (): bool => filled(config('whatsapp.phone_number_id')),
                'WhatsApp access token configurato' => fn (): bool => filled(config('whatsapp.access_token')),
                'WhatsApp app secret configurato' => fn (): bool => filled(config('whatsapp.app_secret')),
                'WhatsApp verify token configurato' => fn (): bool => filled(config('whatsapp.verify_token')),
                'Worker asincrono configurato' => fn (): bool => config('queue.default') !== 'sync',
            ];
        }

        $failed = false;

        foreach ($checks as $label => $check) {
            try {
                $passed = $check();
            } catch (Throwable) {
                $passed = false;
            }

            $this->line(($passed ? 'PASS  ' : 'FAIL  ').$label);
            $failed = $failed || ! $passed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
