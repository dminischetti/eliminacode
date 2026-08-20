<?php

namespace App\Services;

use App\Exceptions\QueueException;
use App\Models\QueueDay;
use Illuminate\Support\Facades\DB;

class QueueService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * §41 - PROSSIMO.
     *
     * L'avanzamento e' un singolo UPDATE condizionale: avanza solo se lo
     * stato nel database e' ancora quello che lo staff aveva sotto gli occhi.
     * Il secondo tap trova current_number gia' cambiato e viene rifiutato,
     * quindi 44 non diventa mai 46 per un doppio tocco (§42).
     */
    public function next(QueueDay $day, int $expectedCurrentNumber): QueueDay
    {
        return DB::transaction(function () use ($day, $expectedCurrentNumber) {
            $affected = DB::table('queue_days')
                ->where('id', $day->id)
                ->where('status', QueueDay::STATUS_OPEN)
                ->where('current_number', $expectedCurrentNumber)
                ->whereColumn('current_number', '<', 'last_issued_number')
                ->update([
                    'current_number' => DB::raw('current_number + 1'),
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                throw $this->explainFailedAdvance($day->id);
            }

            $fresh = QueueDay::whereKey($day->id)->firstOrFail();

            // §47 - unico punto di valutazione dell'eleggibilita' WhatsApp.
            // In Fase 1 non fa nulla. Nessuna chiamata di rete qui dentro.
            $this->notifications->evaluate($fresh);

            return $fresh;
        });
    }

    /**
     * §44-§46 - CORREGGI NUMERO.
     *
     * L'unica funzione straordinaria della V1. Sempre entro i limiti,
     * sempre tracciata, sempre seguita dalla rivalutazione delle notifiche
     * (una correzione in avanti puo' far entrare dei ticket in soglia, §48).
     */
    public function correct(QueueDay $day, int $newCurrentNumber, ?int $staffUserId = null): QueueDay
    {
        return DB::transaction(function () use ($day, $newCurrentNumber, $staffUserId) {
            /** @var QueueDay $locked */
            $locked = QueueDay::whereKey($day->id)->lockForUpdate()->firstOrFail();

            if ($newCurrentNumber < 0 || $newCurrentNumber > $locked->last_issued_number) {
                throw QueueException::correctionOutOfRange($newCurrentNumber, $locked->last_issued_number);
            }

            $oldCurrentNumber = $locked->current_number;

            if ($oldCurrentNumber === $newCurrentNumber) {
                return $locked;
            }

            $locked->current_number = $newCurrentNumber;
            $locked->save();

            DB::table('queue_corrections')->insert([
                'queue_day_id' => $locked->id,
                'staff_user_id' => $staffUserId,
                'old_current_number' => $oldCurrentNumber,
                'new_current_number' => $newCurrentNumber,
                'created_at' => now(),
            ]);

            $this->notifications->evaluate($locked);

            return $locked;
        });
    }

    /**
     * Zero righe aggiornate ha tre cause diverse e tre messaggi diversi
     * per lo staff. Si rilegge lo stato e si sceglie (§42-§43).
     */
    private function explainFailedAdvance(int $queueDayId): QueueException
    {
        $fresh = QueueDay::whereKey($queueDayId)->first();

        return match (true) {
            $fresh === null, ! $fresh->isOpen() => QueueException::closed(),
            ! $fresh->hasWaitingNumbers() => QueueException::noWaitingNumbers(),
            default => QueueException::staleState(),
        };
    }
}
