<?php

namespace App\Services;

use App\Models\QueueDay;
use App\Models\Ticket;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppSender
{
    /**
     * Un messaggio viene tentato al massimo una volta. In caso di timeout
     * l'esito e' ambiguo: ritentare potrebbe consegnare un duplicato, quindi
     * preferiamo registrare il fallimento e lasciare intatta la coda.
     */
    public function send(int $messageId): void
    {
        $message = $this->claim($messageId);

        if ($message === null) {
            return;
        }

        try {
            if (! $this->configurationComplete()) {
                $this->fail($message->id, 'configuration', 'Configurazione WhatsApp incompleta.');

                return;
            }

            $response = Http::withToken((string) config('whatsapp.access_token'))
                ->acceptJson()
                ->connectTimeout((int) config('whatsapp.http_connect_timeout_seconds'))
                ->timeout((int) config('whatsapp.http_timeout_seconds'))
                ->post($this->endpoint(), [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $message->ticket->whatsapp_recipient,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message->body,
                    ],
                ]);

            $metaMessageId = $response->json('messages.0.id');

            if (! $response->successful() || ! is_string($metaMessageId) || $metaMessageId === '') {
                $this->failFromResponse($message->id, $response);

                return;
            }

            WhatsAppMessage::query()
                ->whereKey($message->id)
                ->where('status', WhatsAppMessage::STATUS_PROCESSING)
                ->update([
                    'status' => WhatsAppMessage::STATUS_ACCEPTED,
                    'meta_message_id' => $metaMessageId,
                    'accepted_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::info('Messaggio WhatsApp accettato da Meta.', [
                'whatsapp_message_id' => $message->id,
                'ticket_id' => $message->ticket_id,
            ]);
        } catch (Throwable $e) {
            $this->fail($message->id, 'uncertain_delivery', 'Connessione a Meta non completata.');

            Log::error('Tentativo WhatsApp terminato con esito incerto.', [
                'whatsapp_message_id' => $message->id,
                'ticket_id' => $message->ticket_id,
                'exception' => $e::class,
            ]);
        }
    }

    private function claim(int $messageId): ?WhatsAppMessage
    {
        return DB::transaction(function () use ($messageId) {
            /** @var WhatsAppMessage|null $message */
            $message = WhatsAppMessage::with('ticket.queueDay')->lockForUpdate()->find($messageId);

            if ($message === null
                || $message->status !== WhatsAppMessage::STATUS_PENDING
                || $message->available_at->isFuture()) {
                return null;
            }

            $ticket = $message->ticket;
            $day = $ticket->queueDay;

            if (! config('whatsapp.enabled')
                || ! $ticket->whatsappEnabled()
                || $day->status !== QueueDay::STATUS_OPEN
                || $ticket->state($day->current_number) !== Ticket::STATE_WAITING) {
                $message->forceFill([
                    'status' => WhatsAppMessage::STATUS_CANCELLED,
                    'failure_code' => 'ticket_not_eligible',
                    'failure_reason' => 'Ticket non piu\' in attesa.',
                ])->save();

                return null;
            }

            $message->forceFill([
                'status' => WhatsAppMessage::STATUS_PROCESSING,
                'processing_at' => now(),
                'attempts' => $message->attempts + 1,
            ])->save();

            return $message;
        });
    }

    private function configurationComplete(): bool
    {
        return config('whatsapp.enabled')
            && filled(config('whatsapp.phone_number_id'))
            && filled(config('whatsapp.access_token'));
    }

    private function endpoint(): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            trim((string) config('whatsapp.graph_version'), '/'),
            rawurlencode((string) config('whatsapp.phone_number_id')),
        );
    }

    private function failFromResponse(int $messageId, Response $response): void
    {
        $code = $response->json('error.code');
        $safeCode = is_scalar($code) ? 'meta_'.substr((string) $code, 0, 100) : 'http_'.$response->status();

        $this->fail($messageId, $safeCode, 'Meta ha rifiutato il messaggio (HTTP '.$response->status().').');
    }

    private function fail(int $messageId, string $code, string $reason): void
    {
        WhatsAppMessage::query()
            ->whereKey($messageId)
            ->where('status', WhatsAppMessage::STATUS_PROCESSING)
            ->update([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'failed_at' => now(),
                'failure_code' => $code,
                'failure_reason' => $reason,
                'updated_at' => now(),
            ]);
    }
}
