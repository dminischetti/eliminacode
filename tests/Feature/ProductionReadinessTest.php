<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_il_controllo_operativo_passa_con_i_prerequisiti_presenti(): void
    {
        User::factory()->create();

        $this->artisan('coda:check')
            ->expectsOutput('PASS  Database raggiungibile')
            ->expectsOutput('PASS  Account staff presente')
            ->assertSuccessful();
    }

    public function test_il_controllo_operativo_fallisce_senza_account_staff(): void
    {
        $this->artisan('coda:check')
            ->expectsOutput('FAIL  Account staff presente')
            ->assertFailed();
    }

    public function test_whatsapp_attivo_richiede_credenziali_e_worker_asincrono(): void
    {
        User::factory()->create();
        config([
            'whatsapp.enabled' => true,
            'whatsapp.business_number' => '393331234567',
            'whatsapp.phone_number_id' => '123456789',
            'whatsapp.access_token' => 'token',
            'whatsapp.app_secret' => 'secret',
            'whatsapp.verify_token' => 'verify',
            'queue.default' => 'sync',
        ]);

        $this->artisan('coda:check')
            ->expectsOutput('PASS  WhatsApp numero business configurato')
            ->expectsOutput('FAIL  Worker asincrono configurato')
            ->assertFailed();
    }
}
