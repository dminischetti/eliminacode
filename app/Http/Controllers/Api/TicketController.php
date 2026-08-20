<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\QueueException;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\QueueDayService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly QueueDayService $queueDays,
    ) {}

    /**
     * §24 - La Idempotency-Key arriva dal browser ed e' l'unica cosa che
     * distingue "sto riprovando" da "voglio un altro numero". Senza key
     * valida non si emette nulla: meglio un errore che un numero doppio.
     */
    public function store(Request $request): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if (! preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $key)) {
            return response()->json([
                'message' => 'Richiesta non valida. Ricarica la pagina e riprova.',
            ], 422);
        }

        try {
            $ticket = $this->tickets->issue($key);
        } catch (QueueException $e) {
            return response()->json(['message' => $e->getMessage()], $e->httpStatus());
        }

        return response()->json(
            $this->payload($ticket) + ['public_token' => $ticket->public_token],
            $ticket->wasRecentlyCreated ? 201 : 200
        );
    }

    /** §31 */
    public function status(string $token): JsonResponse
    {
        $ticket = $this->tickets->findByToken($token);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket non trovato.'], 404);
        }

        return response()->json($this->payload($ticket));
    }

    /**
     * Lo stato del ticket e i numeri mancanti sono calcolati qui, non nel
     * browser: la formula vive in un posto solo (Ticket::state / remaining).
     *
     * "today" e' la business date corrente secondo il server, "business_date"
     * quella del ticket. Se differiscono il ticket e' di un giorno passato e
     * il client lo scarta senza dover ricaricare la pagina (§21).
     */
    private function payload(Ticket $ticket): array
    {
        $day = $ticket->queueDay;

        return [
            'today' => $this->queueDays->businessDate(),
            'business_date' => $day->businessDateString(),
            'queue_status' => $day->status,
            'current_number' => $day->current_number,
            'ticket_number' => $ticket->number,
            'ticket_state' => $ticket->state($day->current_number),
            'remaining' => $ticket->remaining($day->current_number),
        ];
    }
}
