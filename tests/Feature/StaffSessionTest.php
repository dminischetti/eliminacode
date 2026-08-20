<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lo_staff_puo_accedere_e_uscire(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-sicura')]);

        $this->post('/staff/login', [
            'email' => $user->email,
            'password' => 'password-sicura',
        ])->assertRedirect('/staff');

        $this->assertAuthenticatedAs($user);

        $this->post('/staff/logout')->assertRedirect('/staff/login');
        $this->assertGuest();
    }

    public function test_una_password_errata_non_autentica(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-sicura')]);

        $this->from('/staff/login')->post('/staff/login', [
            'email' => $user->email,
            'password' => 'errata',
        ])->assertRedirect('/staff/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_il_comando_staff_valida_email_e_normalizza_i_dati(): void
    {
        $this->artisan('coda:staff', ['email' => 'email-non-valida'])
            ->expectsOutput('Indirizzo email non valido.')
            ->assertFailed();

        $this->artisan('coda:staff', ['email' => '  BANCO@EXAMPLE.COM ', '--name' => ' Banco '])
            ->expectsQuestion('Password', 'password-sicura')
            ->expectsQuestion('Conferma password', 'password-sicura')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'banco@example.com',
            'name' => 'Banco',
        ]);
        $this->assertNotNull(User::firstOrFail()->remember_token);
    }
}
