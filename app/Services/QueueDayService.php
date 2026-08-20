<?php

namespace App\Services;

use App\Models\QueueDay;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class QueueDayService
{
    /** §7 - la business date non dipende mai dal timezone del server. */
    public function businessDate(): string
    {
        return Carbon::now(config('queue_shop.timezone'))->toDateString();
    }

    /**
     * §9-§11 - Risolve la giornata corrente, creandola se non esiste.
     *
     * ATTENZIONE: da chiamare sempre FUORI da una transazione. Su PostgreSQL
     * una violazione di unique dentro una transazione la aborta, e il retry
     * del §10 non sarebbe piu' possibile.
     *
     * Non ricrea mai una giornata gia' chiusa: resta chiusa finche' lo staff
     * non usa RIAPRI GIORNATA o non cambia la business date.
     */
    public function today(): QueueDay
    {
        $date = $this->businessDate();

        if ($day = QueueDay::where('business_date', $date)->first()) {
            return $day;
        }

        try {
            return QueueDay::create([
                'business_date' => $date,
                'status' => QueueDay::STATUS_OPEN,
                'current_number' => 0,
                'last_issued_number' => 0,
                'opened_at' => now(),
                'closed_at' => null,
            ]);
        } catch (QueryException $e) {
            // §10 - un'altra richiesta ha creato la giornata un istante prima.
            // Il perdente non deve mai restituire un errore al cliente.
            if ($day = QueueDay::where('business_date', $date)->first()) {
                return $day;
            }

            throw $e;
        }
    }

    /** §12 */
    public function close(QueueDay $day): QueueDay
    {
        $day->forceFill([
            'status' => QueueDay::STATUS_CLOSED,
            'closed_at' => now(),
        ])->save();

        return $day;
    }

    /**
     * §13 - La riapertura non tocca i contatori: la coda riprende
     * esattamente dal punto in cui era stata chiusa per errore.
     */
    public function reopen(QueueDay $day): QueueDay
    {
        $day->forceFill([
            'status' => QueueDay::STATUS_OPEN,
            'closed_at' => null,
        ])->save();

        return $day;
    }
}
