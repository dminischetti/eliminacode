<?php

namespace Tests\Feature;

use App\Models\QueueDay;
use App\Models\User;
use App\Services\QueueDayService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create();
    }

    private function emetti(int $quanti = 1): void
    {
        for ($i = 0; $i < $quanti; $i++) {
            app(TicketService::class)->issue((string) Str::uuid());
        }
    }

    public function test_larea_staff_richiede_autenticazione(): void
    {
        $this->get('/staff')->assertRedirect('/staff/login');
        $this->postJson('/api/staff/queue/next', ['expected_current_number' => 0])
            ->assertUnauthorized();
    }

    public function test_la_dashboard_mostra_lo_stato_della_coda(): void
    {
        $this->emetti(3);

        $this->actingAs($this->staff)
            ->get('/staff')
            ->assertOk()
            ->assertSee('Ora serviamo');

        $this->actingAs($this->staff)
            ->getJson('/api/staff/queue')
            ->assertJsonPath('current_number', 0)
            ->assertJsonPath('last_issued_number', 3)
            ->assertJsonPath('has_waiting', true);
    }

    public function test_prossimo_avanza_di_uno(): void
    {
        $this->emetti(2);

        $this->actingAs($this->staff)
            ->postJson('/api/staff/queue/next', ['expected_current_number' => 0])
            ->assertOk()
            ->assertJsonPath('current_number', 1);
    }

    /** Il doppio tap parte con lo stesso expected e va rifiutato. */
    public function test_il_secondo_prossimo_con_expected_vecchio_viene_rifiutato(): void
    {
        $this->emetti(3);

        $this->actingAs($this->staff)
            ->postJson('/api/staff/queue/next', ['expected_current_number' => 0])
            ->assertOk();

        $this->actingAs($this->staff)
            ->postJson('/api/staff/queue/next', ['expected_current_number' => 0])
            ->assertStatus(409)
            ->assertJsonPath('queue.current_number', 1);

        $this->assertSame(1, app(QueueDayService::class)->today()->current_number);
    }

    public function test_prossimo_a_coda_vuota_viene_rifiutato(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/api/staff/queue/next', ['expected_current_number' => 0])
            ->assertStatus(409);
    }

    public function test_correzione_valida_e_registrata(): void
    {
        $this->emetti(3);

        $this->actingAs($this->staff)->postJson('/api/staff/queue/next', ['expected_current_number' => 0]);
        $this->actingAs($this->staff)->postJson('/api/staff/queue/next', ['expected_current_number' => 1]);

        $this->actingAs($this->staff)
            ->postJson('/api/staff/queue/correct', ['new_current_number' => 1])
            ->assertOk()
            ->assertJsonPath('current_number', 1);

        $this->assertDatabaseHas('queue_corrections', [
            'staff_user_id' => $this->staff->id,
            'old_current_number' => 2,
            'new_current_number' => 1,
        ]);
    }

    public function test_correzione_oltre_lultimo_numero_emesso_rifiutata(): void
    {
        $this->emetti(2);

        $this->actingAs($this->staff)
            ->postJson('/api/staff/queue/correct', ['new_current_number' => 9])
            ->assertStatus(422)
            ->assertJsonPath('queue.current_number', 0);
    }

    public function test_chiusura_e_riapertura_conservano_i_contatori(): void
    {
        $this->emetti(4);
        $this->actingAs($this->staff)->postJson('/api/staff/queue/next', ['expected_current_number' => 0]);

        $this->actingAs($this->staff)
            ->postJson('/api/staff/queue/close', [])
            ->assertOk()
            ->assertJsonPath('queue_status', QueueDay::STATUS_CLOSED);

        // Con la coda chiusa il cliente non prende numeri.
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/tickets')
            ->assertStatus(409);

        $this->actingAs($this->staff)
            ->postJson('/api/staff/queue/reopen', [])
            ->assertOk()
            ->assertJsonPath('queue_status', QueueDay::STATUS_OPEN)
            ->assertJsonPath('current_number', 1)
            ->assertJsonPath('last_issued_number', 4);
    }
}
