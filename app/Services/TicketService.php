<?php

namespace App\Services;

use App\Exceptions\QueueException;
use App\Models\QueueDay;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(
        private readonly QueueDayService $queueDays,
    ) {
    }

    /**
     * §24 - Emissione atomica.
     *
     * La stessa idempotency_key restituisce sempre lo stesso ticket dentro
     * la stessa giornata: e' cio' che rende sicuri il doppio tap, il retry
     * dopo timeout e la riapertura del browser durante una richiesta (§16-§19).
     *
     * Il lock sulla riga QueueDay serializza l'assegnazione del numero: a
     * questi volumi e' gratuito ed elimina ogni possibilita' di numero doppio.
     */
    public function issue(string $idempotencyKey): Ticket
    {
        // Risolto fuori dalla transazione, per il motivo spiegato in QueueDayService::today().
        $queueDayId = $this->queueDays->today()->id;

        return DB::transaction(function () use ($queueDayId, $idempotencyKey) {
            /** @var QueueDay $day */
            $day = QueueDay::whereKey($queueDayId)->lockForUpdate()->firstOrFail();

            $existing = Ticket::where('queue_day_id', $day->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // La giornata puo' essere stata chiusa tra la risoluzione e il lock.
            if (! $day->isOpen()) {
                throw QueueException::closed();
            }

            $day->last_issued_number = $day->last_issued_number + 1;
            $day->save();

            return Ticket::create([
                'queue_day_id' => $day->id,
                'number' => $day->last_issued_number,
                'public_token' => $this->generateToken(),
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    public function findByToken(string $publicToken): ?Ticket
    {
        return Ticket::with('queueDay')->where('public_token', $publicToken)->first();
    }

    /** §25 - 128 bit, non incrementale, non derivato dal numero del ticket. */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
