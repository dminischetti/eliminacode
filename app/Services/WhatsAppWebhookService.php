<?php

namespace App\Services;

use App\Models\WhatsAppMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookService
{
    public function __construct(
        private readonly WhatsAppAssociationService $associations,
    ) {}

    public function process(array $payload): void
    {
        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return;
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['messages'] ?? [] as $message) {
                    $this->processInbound($message);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->processStatus($status);
                }
            }
        }
    }

    private function processInbound(array $message): void
    {
        $messageId = $message['id'] ?? null;
        $sender = $message['from'] ?? null;
        $body = $message['text']['body'] ?? null;

        if (! is_string($messageId) || $messageId === '') {
            return;
        }

        DB::transaction(function () use ($messageId, $sender, $body): void {
            if (! $this->rememberEvent('message:'.$messageId, 'inbound_message')) {
                return;
            }

            if (! is_string($sender)
                || ! preg_match('/^[0-9]{5,32}$/', $sender)
                || ! is_string($body)) {
                return;
            }

            $ticket = $this->associations->associateInbound($sender, $body);

            if ($ticket !== null) {
                Log::info('Associazione WhatsApp completata.', ['ticket_id' => $ticket->id]);
            }
        });
    }

    private function processStatus(array $status): void
    {
        $metaMessageId = $status['id'] ?? null;
        $state = $status['status'] ?? null;
        $timestamp = $status['timestamp'] ?? null;

        if (! is_string($metaMessageId) || ! is_string($state)) {
            return;
        }

        $eventKey = 'status:'.hash('sha256', $metaMessageId.'|'.$state.'|'.(string) $timestamp);

        DB::transaction(function () use ($eventKey, $metaMessageId, $state, $timestamp, $status): void {
            if (! $this->rememberEvent($eventKey, 'message_status')) {
                return;
            }

            /** @var WhatsAppMessage|null $message */
            $message = WhatsAppMessage::where('meta_message_id', $metaMessageId)->lockForUpdate()->first();

            if ($message === null) {
                return;
            }

            $occurredAt = is_numeric($timestamp)
                ? CarbonImmutable::createFromTimestampUTC((int) $timestamp)
                : now();

            $updates = match ($state) {
                'sent' => [
                    'status' => $this->laterStatus($message->status, WhatsAppMessage::STATUS_ACCEPTED),
                    'accepted_at' => $message->accepted_at ?? $occurredAt,
                    'failed_at' => null,
                    'failure_code' => null,
                    'failure_reason' => null,
                ],
                'delivered' => [
                    'status' => $this->laterStatus($message->status, WhatsAppMessage::STATUS_DELIVERED),
                    'delivered_at' => $message->delivered_at ?? $occurredAt,
                    'failed_at' => null,
                    'failure_code' => null,
                    'failure_reason' => null,
                ],
                'read' => [
                    'status' => WhatsAppMessage::STATUS_READ,
                    'read_at' => $message->read_at ?? $occurredAt,
                    'failed_at' => null,
                    'failure_code' => null,
                    'failure_reason' => null,
                ],
                'failed' => $this->failureUpdates($message, $status, $occurredAt),
                default => [],
            };

            if ($updates !== []) {
                $message->forceFill($updates)->save();
            }
        });
    }

    private function rememberEvent(string $eventKey, string $eventType): bool
    {
        return DB::table('whatsapp_webhook_events')->insertOrIgnore([
            'event_key' => substr($eventKey, 0, 191),
            'event_type' => $eventType,
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    private function laterStatus(string $current, string $candidate): string
    {
        $rank = [
            WhatsAppMessage::STATUS_PENDING => 0,
            WhatsAppMessage::STATUS_PROCESSING => 1,
            WhatsAppMessage::STATUS_ACCEPTED => 2,
            WhatsAppMessage::STATUS_DELIVERED => 3,
            WhatsAppMessage::STATUS_READ => 4,
        ];

        return ($rank[$candidate] ?? 0) > ($rank[$current] ?? 0) ? $candidate : $current;
    }

    private function failureUpdates(WhatsAppMessage $message, array $status, $occurredAt): array
    {
        // Una ricevuta delivered/read non viene retrocessa da un evento tardivo.
        if (in_array($message->status, [WhatsAppMessage::STATUS_DELIVERED, WhatsAppMessage::STATUS_READ], true)) {
            return [];
        }

        $code = $status['errors'][0]['code'] ?? 'unknown';

        return [
            'status' => WhatsAppMessage::STATUS_FAILED,
            'failed_at' => $message->failed_at ?? $occurredAt,
            'failure_code' => 'meta_'.substr((string) $code, 0, 100),
            'failure_reason' => 'Meta ha segnalato la mancata consegna.',
        ];
    }
}
