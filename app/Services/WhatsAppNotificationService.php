<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessage;
use App\Models\QueueDay;
use App\Models\Ticket;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppNotificationService
{
    /**
     * Crea l'evento logico una sola volta. Nessuna chiamata a Meta avviene
     * qui: PROSSIMO rimane veloce e indipendente dal servizio esterno.
     *
     * @return Collection<int, int> ID dei messaggi ancora da elaborare
     */
    public function scheduleEligible(QueueDay $day): Collection
    {
        if (! config('whatsapp.enabled') || ! $day->isOpen()) {
            return collect();
        }

        $threshold = (int) config('whatsapp.warning_threshold');

        $tickets = Ticket::query()
            ->where('queue_day_id', $day->id)
            ->whereNotNull('whatsapp_enabled_at')
            ->whereNotNull('whatsapp_recipient')
            ->where('number', '>', $day->current_number)
            ->where('number', '<=', $day->current_number + $threshold)
            ->get();

        $messageIds = collect();

        foreach ($tickets as $ticket) {
            // insertOrIgnore rende innocua anche la rara corsa tra recovery
            // schedulato e richiesta staff sullo stesso ticket.
            DB::table('whatsapp_messages')->insertOrIgnore([
                'ticket_id' => $ticket->id,
                'logical_type' => WhatsAppMessage::TYPE_APPROACHING_TURN,
                'direction' => 'outbound',
                'status' => WhatsAppMessage::STATUS_PENDING,
                'current_number_snapshot' => $day->current_number,
                'body' => $this->body($day->current_number, $ticket->number),
                'attempts' => 0,
                'available_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $message = WhatsAppMessage::query()
                ->where('ticket_id', $ticket->id)
                ->where('logical_type', WhatsAppMessage::TYPE_APPROACHING_TURN)
                ->firstOrFail();

            if ($message->status === WhatsAppMessage::STATUS_PENDING) {
                $messageIds->push($message->id);
            }
        }

        return $messageIds->unique()->values();
    }

    /** @param iterable<int> $messageIds */
    public function dispatch(iterable $messageIds): void
    {
        // Evita che una configurazione accidentale "sync" chiami Meta dentro
        // la richiesta staff. Il comando di recupero potra' inviare in seguito.
        if (config('queue.default') === 'sync') {
            return;
        }

        foreach ($messageIds as $messageId) {
            try {
                SendWhatsAppMessage::dispatch((int) $messageId)
                    ->onQueue((string) config('whatsapp.queue'));
            } catch (Throwable $e) {
                Log::error('Impossibile accodare il messaggio WhatsApp.', [
                    'whatsapp_message_id' => (int) $messageId,
                    'exception' => $e::class,
                ]);
            }
        }
    }

    private function body(int $currentNumber, int $ticketNumber): string
    {
        return "Ci siamo quasi.\n"
            ."Stiamo servendo il numero {$currentNumber} e tu hai il numero {$ticketNumber}.\n"
            .'Torna vicino al banco carni.';
    }
}
