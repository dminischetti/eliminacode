<?php

namespace Tests\Feature;

use App\Http\Middleware\AttachClientId;
use App\Models\QueueDay;
use App\Services\QueueDayService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }

    private function prendiNumero(?string $key = null)
    {
        return $this->withHeader('Idempotency-Key', $key ?? $this->key())
            ->postJson('/api/tickets');
    }

    public function test_la_homepage_apre_la_giornata_e_mostra_il_numero_servito(): void
    {
        $this->get('/')->assertOk()->assertSee('Ora serviamo');

        $this->assertSame(1, QueueDay::count());
    }

    public function test_prendere_un_numero_restituisce_ticket_e_token(): void
    {
        $response = $this->prendiNumero();

        $response->assertCreated()
            ->assertJsonPath('ticket_number', 1)
            ->assertJsonPath('ticket_state', 'waiting')
            ->assertJsonPath('remaining', 0)
            ->assertJsonStructure(['public_token', 'today', 'business_date', 'queue_status']);
    }

    public function test_la_stessa_key_non_crea_un_secondo_numero(): void
    {
        $key = $this->key();

        $first = $this->prendiNumero($key)->json();
        $second = $this->prendiNumero($key)->json();

        $this->assertSame($first['public_token'], $second['public_token']);
        $this->assertSame(1, app(QueueDayService::class)->today()->last_issued_number);
    }

    public function test_un_retry_dopo_la_chiusura_restituisce_il_ticket_originale(): void
    {
        $key = $this->key();
        $first = $this->prendiNumero($key)->assertCreated();

        $days = app(QueueDayService::class);
        $days->close($days->today());

        $this->prendiNumero($key)
            ->assertOk()
            ->assertJsonPath('public_token', $first->json('public_token'));

        $this->prendiNumero()->assertStatus(409);
        $this->assertSame(1, $days->today()->last_issued_number);
    }

    public function test_senza_idempotency_key_valida_non_si_emette_nulla(): void
    {
        $this->withHeader('Idempotency-Key', 'non-una-uuid')
            ->postJson('/api/tickets')
            ->assertStatus(422);

        $this->assertSame(0, app(QueueDayService::class)->today()->last_issued_number);
    }

    public function test_lo_stato_segue_lavanzamento_della_coda(): void
    {
        $token = $this->prendiNumero()->json('public_token');
        $this->prendiNumero();

        $this->getJson("/api/tickets/{$token}/status")
            ->assertOk()
            ->assertJsonPath('ticket_state', 'waiting');

        $days = app(QueueDayService::class);
        app(QueueService::class)->next($days->today(), 0);

        $this->getJson("/api/tickets/{$token}/status")
            ->assertJsonPath('ticket_state', 'called')
            ->assertJsonPath('current_number', 1);

        app(QueueService::class)->next($days->today(), 1);

        $this->getJson("/api/tickets/{$token}/status")
            ->assertJsonPath('ticket_state', 'passed');
    }

    public function test_a_giornata_chiusa_lemissione_e_rifiutata(): void
    {
        $days = app(QueueDayService::class);
        $days->close($days->today());

        $this->prendiNumero()->assertStatus(409);

        $this->getJson('/api/queue')->assertJsonPath('queue_status', 'closed');
    }

    public function test_token_inesistente_restituisce_404(): void
    {
        $this->getJson('/api/tickets/inesistente/status')->assertNotFound();
    }

    public function test_le_rotte_cliente_non_avviano_una_sessione_e_includono_header_sicuri(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertCookie('coda_cid')
            ->assertCookieMissing((string) config('session.cookie'))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'same-origin');

        $this->assertStringContainsString(
            "frame-ancestors 'none'",
            (string) $response->headers->get('Content-Security-Policy')
        );
    }

    public function test_un_singolo_browser_viene_limitato(): void
    {
        // Senza il middleware identificativo, il limiter usa il fallback IP.
        // Questo rende deterministico il test dell'integrazione HTTP; gli altri
        // test verificano separatamente emissione e isolamento dei browser id.
        $this->withoutMiddleware(AttachClientId::class);

        for ($i = 0; $i < 10; $i++) {
            $this->withHeader('Idempotency-Key', $this->key())
                ->postJson('/api/tickets')
                ->assertCreated();
        }

        $this->withHeader('Idempotency-Key', $this->key())
            ->postJson('/api/tickets')
            ->assertTooManyRequests();
    }

    /** Il client scarta il ticket confrontando business_date con today. */
    public function test_un_ticket_di_ieri_riporta_una_business_date_diversa_da_today(): void
    {
        $ieri = QueueDay::create([
            'business_date' => now(config('queue_shop.timezone'))->subDay()->toDateString(),
            'status' => QueueDay::STATUS_OPEN,
            'current_number' => 0,
            'last_issued_number' => 1,
            'opened_at' => now()->subDay(),
        ]);

        $ticket = $ieri->tickets()->create([
            'number' => 1,
            'public_token' => 'token-di-ieri',
            'idempotency_key' => $this->key(),
        ]);

        $response = $this->getJson("/api/tickets/{$ticket->public_token}/status")->assertOk();

        $this->assertNotSame($response->json('business_date'), $response->json('today'));
    }

    public function test_il_rate_limit_non_blocca_venti_clienti_reali(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->withHeaders([
                'Idempotency-Key' => $this->key(),
                // ogni cliente ha il proprio identificativo browser
            ])->withCookie('coda_cid', bin2hex(random_bytes(16)))
                ->postJson('/api/tickets')
                ->assertCreated();
        }

        $this->assertSame(20, app(QueueDayService::class)->today()->last_issued_number);
    }
}
