<?php

namespace App\Console\Commands;

use App\Models\QueueDay;
use App\Models\Ticket;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppNotificationService;
use App\Services\WhatsAppSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverWhatsAppNotifications extends Command
{
    protected $signature = 'coda:whatsapp-recover
        {--send-now : Invia i messaggi pendenti nel processo corrente}';

    protected $description = 'Recupera notifiche WhatsApp pendenti e rimuove identificativi scaduti';

    public function handle(
        WhatsAppNotificationService $notifications,
        WhatsAppSender $sender,
    ): int {
        if (! config('whatsapp.enabled')) {
            $this->line('WhatsApp disabilitato: nessuna operazione.');

            return self::SUCCESS;
        }

        $this->closeUncertainAttempts();

        $today = now((string) config('queue_shop.timezone'))->toDateString();
        $day = QueueDay::whereDate('business_date', $today)->first();

        if ($day !== null && $day->current_number > 0) {
            $notifications->scheduleEligible($day);
        }

        $pending = WhatsAppMessage::query()
            ->where('status', WhatsAppMessage::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit(50)
            ->pluck('id');

        if ($this->option('send-now') || config('queue.default') === 'sync') {
            foreach ($pending as $messageId) {
                $sender->send((int) $messageId);
            }
        } else {
            $notifications->dispatch($pending);
        }

        $purged = $this->purgeExpiredIdentifiers();
        $this->purgeOldWebhookEvents();

        $this->info(sprintf(
            'Recupero completato: %d messaggi pendenti, %d identificativi rimossi.',
            $pending->count(),
            $purged,
        ));

        return self::SUCCESS;
    }

    private function closeUncertainAttempts(): void
    {
        $cutoff = now()->subSeconds((int) config('whatsapp.processing_timeout_seconds'));

        WhatsAppMessage::query()
            ->where('status', WhatsAppMessage::STATUS_PROCESSING)
            ->where('processing_at', '<=', $cutoff)
            ->update([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'failed_at' => now(),
                'failure_code' => 'uncertain_delivery',
                'failure_reason' => 'Worker interrotto durante il tentativo; invio non ripetuto.',
                'updated_at' => now(),
            ]);
    }

    private function purgeExpiredIdentifiers(): int
    {
        $cutoff = now()->subDays((int) config('whatsapp.identifier_retention_days'));

        $purged = Ticket::query()
            ->whereNotNull('whatsapp_recipient')
            ->where('whatsapp_enabled_at', '<=', $cutoff)
            ->update([
                'whatsapp_recipient' => null,
                'whatsapp_enabled_at' => null,
                'whatsapp_identifier_purged_at' => now(),
                'updated_at' => now(),
            ]);

        Ticket::query()
            ->whereNotNull('whatsapp_association_expires_at')
            ->where('whatsapp_association_expires_at', '<=', now())
            ->update([
                'whatsapp_association_token_hash' => null,
                'whatsapp_association_expires_at' => null,
                'updated_at' => now(),
            ]);

        return $purged;
    }

    private function purgeOldWebhookEvents(): void
    {
        DB::table('whatsapp_webhook_events')
            ->where('processed_at', '<=', now()->subDays((int) config('whatsapp.webhook_retention_days')))
            ->delete();
    }
}
