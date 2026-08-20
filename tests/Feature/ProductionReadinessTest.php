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
}
