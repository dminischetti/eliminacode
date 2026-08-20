<?php

namespace Tests\Feature;

use App\Exceptions\QueueException;
use App\Models\QueueDay;
use App\Services\QueueDayService;
use App\Services\QueueService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * I nomi dei test sono le invarianti della specifica (§103).
 * Ogni volta che ne aggiungi una, qui nasce un test.
 */
class QueueCoreTest extends TestCase
{
    use RefreshDatabase;

    private QueueDayService $days;
    private TicketService $tickets;
    private QueueService $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->days = app(QueueDayService::class);
        $this->tickets = app(TicketService::class);
        $this->queue = app(QueueService::class);
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }

    /** Invariante 12: un retry idempotente restituisce lo stesso ticket. */
    public function test_stessa_key_stesso_ticket(): void
    {
        $key = $this->key();

        $first = $this->tickets->issue($key);
        $second = $this->tickets->issue($key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->number, $second->number);
        $this->assertSame(1, $this->days->today()->last_issued_number);
    }

    /** Invariante 4: numeri progressivi e unici nella giornata. */
    public function test_intenzioni_diverse_ricevono_numeri_progressivi(): void
    {
        $a = $this->tickets->issue($this->key());
        $b = $this->tickets->issue($this->key());
        $c = $this->tickets->issue($this->key());

        $this->assertSame([1, 2, 3], [$a->number, $b->number, $c->number]);
        $this->assertNotSame($a->public_token, $b->public_token);
    }

    /** Invarianti 13-14: il doppio tap non salta un cliente. */
    public function test_il_doppio_prossimo_non_avanza_due_volte(): void
    {
        $this->tickets->issue($this->key());
        $this->tickets->issue($this->key());

        $this->queue->next($this->days->today(), 0);

        try {
            // Il secondo tap parte dalla stessa schermata, quindi con lo stesso expected.
            $this->queue->next($this->days->today(), 0);
            $this->fail('Il secondo PROSSIMO doveva essere rifiutato.');
        } catch (QueueException) {
            // atteso
        }

        $this->assertSame(1, $this->days->today()->current_number);
    }

    /** Invariante 15: PROSSIMO non supera l'ultimo numero emesso. */
    public function test_prossimo_a_coda_vuota_non_incrementa(): void
    {
        $this->tickets->issue($this->key());
        $this->queue->next($this->days->today(), 0);

        $this->expectException(QueueException::class);
        $this->queue->next($this->days->today(), 1);
    }

    /** Invarianti 17-18: chiusura e riapertura non toccano i contatori. */
    public function test_riapertura_conserva_i_contatori(): void
    {
        $this->tickets->issue($this->key());
        $this->tickets->issue($this->key());
        $this->queue->next($this->days->today(), 0);

        $this->days->close($this->days->today());
        $this->assertSame(QueueDay::STATUS_CLOSED, $this->days->today()->status);

        $this->days->reopen($this->days->today());
        $reopened = $this->days->today();

        $this->assertSame(QueueDay::STATUS_OPEN, $reopened->status);
        $this->assertSame(1, $reopened->current_number);
        $this->assertSame(2, $reopened->last_issued_number);
        $this->assertNull($reopened->closed_at);
    }

    /** §9: a giornata chiusa non si emettono ticket e non se ne crea una seconda. */
    public function test_giornata_chiusa_non_emette_ticket(): void
    {
        $this->days->close($this->days->today());

        try {
            $this->tickets->issue($this->key());
            $this->fail('Non doveva essere emesso alcun ticket.');
        } catch (QueueException) {
            // atteso
        }

        $this->assertSame(1, QueueDay::count());
    }

    /** Invariante 16: la correzione resta nei limiti. */
    public function test_correzione_fuori_range_rifiutata(): void
    {
        $this->tickets->issue($this->key());

        $this->expectException(QueueException::class);
        $this->queue->correct($this->days->today(), 5, 0);
    }

    public function test_correzione_valida_viene_applicata_e_tracciata(): void
    {
        $this->tickets->issue($this->key());
        $this->tickets->issue($this->key());
        $this->queue->next($this->days->today(), 0);
        $this->queue->next($this->days->today(), 1);

        $this->queue->correct($this->days->today(), 1, 2);

        $this->assertSame(1, $this->days->today()->current_number);
        $this->assertDatabaseHas('queue_corrections', [
            'old_current_number' => 2,
            'new_current_number' => 1,
        ]);
    }

    public function test_correzione_con_stato_vecchio_viene_rifiutata(): void
    {
        $this->tickets->issue($this->key());
        $this->queue->next($this->days->today(), 0);

        $this->expectException(QueueException::class);
        $this->queue->correct($this->days->today(), 0, 0);
    }

    public function test_correzione_a_giornata_chiusa_viene_rifiutata(): void
    {
        $this->tickets->issue($this->key());
        $this->days->close($this->days->today());

        $this->expectException(QueueException::class);
        $this->queue->correct($this->days->today(), 0, 0);
    }

    /** §15: lo stato del ticket e' derivato, non memorizzato. */
    public function test_stato_derivato_e_numeri_mancanti(): void
    {
        $ticket = $this->tickets->issue($this->key());
        $ticket->number = 48;

        $this->assertSame('waiting', $ticket->state(44));
        $this->assertSame(3, $ticket->remaining(44));

        $this->assertSame('called', $ticket->state(48));
        $this->assertSame(0, $ticket->remaining(48));

        $this->assertSame('passed', $ticket->state(49));
        $this->assertSame(0, $ticket->remaining(49));
    }
}
