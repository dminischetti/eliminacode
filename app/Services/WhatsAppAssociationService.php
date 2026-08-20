<?php

namespace App\Services;

use App\Models\QueueDay;
use App\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;

class WhatsAppAssociationService
{
    public function isAvailable(): bool
    {
        return (bool) config('whatsapp.enabled')
            && filled(config('whatsapp.business_number'));
    }

    /**
     * Genera un token monouso separato dal token pubblico del ticket.
     * Nel database resta solo l'hash: il valore grezzo vive nel link wa.me.
     */
    public function createLink(Ticket $ticket): string
    {
        $ticket->loadMissing('queueDay');

        if (! $this->isAvailable()) {
            throw new DomainException('Il servizio WhatsApp non e\' ancora disponibile.');
        }

        if ($ticket->whatsappEnabled()) {
            throw new DomainException('L\'avviso WhatsApp e\' gia\' attivo.');
        }

        if (! $this->isEligible($ticket)) {
            throw new DomainException('Questo numero non puo\' piu\' attivare l\'avviso WhatsApp.');
        }

        $rawToken = $this->generateToken();
        $ticket->forceFill([
            'whatsapp_association_token_hash' => hash('sha256', $rawToken),
            'whatsapp_association_expires_at' => now()->addMinutes((int) config('whatsapp.association_minutes')),
            'whatsapp_identifier_purged_at' => null,
        ])->save();

        $text = sprintf(
            "Avvisami quando si avvicina il turno %d.\n\nCodice: CODA-%s",
            $ticket->number,
            $rawToken,
        );

        return sprintf(
            'https://wa.me/%s?text=%s',
            config('whatsapp.business_number'),
            rawurlencode($text),
        );
    }

    /**
     * L'associazione nasce solo dal messaggio realmente ricevuto da Meta.
     * Messaggi liberi o token scaduti vengono ignorati senza creare chatbot.
     */
    public function associateInbound(string $sender, string $body): ?Ticket
    {
        if (! preg_match('/\bCODA-([A-Za-z0-9_-]{32})(?![A-Za-z0-9_-])/', $body, $matches)) {
            return null;
        }

        $hash = hash('sha256', $matches[1]);

        return DB::transaction(function () use ($hash, $sender) {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::with('queueDay')
                ->where('whatsapp_association_token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($ticket === null
                || $ticket->whatsapp_association_expires_at?->isPast()
                || ! $this->isEligible($ticket)) {
                return null;
            }

            $ticket->forceFill([
                'whatsapp_recipient' => $sender,
                'whatsapp_enabled_at' => now(),
                'whatsapp_association_token_hash' => null,
                'whatsapp_association_expires_at' => null,
                'whatsapp_identifier_purged_at' => null,
            ])->save();

            return $ticket;
        });
    }

    private function isEligible(Ticket $ticket): bool
    {
        $day = $ticket->queueDay;
        $today = now((string) config('queue_shop.timezone'))->toDateString();

        return $day->status === QueueDay::STATUS_OPEN
            && $day->businessDateString() === $today
            && $ticket->state($day->current_number) === Ticket::STATE_WAITING;
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }
}
