<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\WhatsAppMessage;
use App\Services\QueueDayService;
use App\Services\QueueService;
use App\Services\TicketService;
use App\Services\WhatsAppSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-app-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'whatsapp.enabled' => true,
            'whatsapp.business_number' => '393331234567',
            'whatsapp.phone_number_id' => '123456789',
            'whatsapp.access_token' => 'test-access-token',
            'whatsapp.app_secret' => self::APP_SECRET,
            'whatsapp.verify_token' => 'test-verify-token',
            'whatsapp.graph_version' => 'v25.0',
            'whatsapp.warning_threshold' => 2,
            'queue.default' => 'sync',
        ]);
    }

    public function test_aprire_il_link_non_attiva_lavviso_finche_il_messaggio_non_arriva(): void
    {
        $ticket = $this->issue();

        $response = $this->postJson("/api/tickets/{$ticket->public_token}/whatsapp-link")
            ->assertOk()
            ->assertJsonPath('status', 'awaiting_message');

        $this->assertStringStartsWith('https://wa.me/393331234567?text=', $response->json('url'));
        $this->assertNotNull($ticket->fresh()->whatsapp_association_token_hash);
        $this->assertNull($ticket->fresh()->whatsapp_enabled_at);

        $this->getJson("/api/tickets/{$ticket->public_token}/status")
            ->assertJsonPath('whatsapp.status', 'inactive');
    }

    public function test_un_messaggio_valido_associa_il_ticket_e_un_retry_webhook_e_sicuro(): void
    {
        $ticket = $this->issue();
        $token = $this->associationToken($ticket);
        $payload = $this->inboundPayload('wamid.inbound-1', '393401112233', "Codice: CODA-{$token}");

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->whatsappEnabled());
        $this->assertSame('393401112233', $fresh->whatsapp_recipient);
        $this->assertNotSame(
            '393401112233',
            DB::table('tickets')->where('id', $ticket->id)->value('whatsapp_recipient'),
        );
        $this->assertNull($fresh->whatsapp_association_token_hash);
        $this->assertDatabaseCount('whatsapp_webhook_events', 1);

        $this->getJson("/api/tickets/{$ticket->public_token}/status")
            ->assertJsonPath('whatsapp.status', 'active')
            ->assertJsonMissing(['whatsapp_recipient' => '393401112233']);
    }

    public function test_messaggi_liberi_e_firme_non_valide_non_associano_nulla(): void
    {
        $ticket = $this->issue();
        $this->associationToken($ticket);

        $payload = $this->inboundPayload('wamid.inbound-2', '393401112233', 'Ciao, quando tocca a me?');
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/webhooks/whatsapp',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=errata'],
            content: $json,
        )->assertForbidden();

        $this->postSignedWebhook($payload)->assertOk();

        $this->assertFalse($ticket->fresh()->whatsappEnabled());
    }

    public function test_meta_puo_verificare_il_callback(): void
    {
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=test-verify-token&hub_challenge=98765')
            ->assertOk()
            ->assertSeeText('98765');

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=errato&hub_challenge=98765')
            ->assertForbidden();
    }

    public function test_lavanzamento_crea_un_solo_avviso_e_non_chiama_meta_nella_richiesta_staff(): void
    {
        Http::fake();
        $this->issue();
        $this->issue();
        $ticket = $this->issue();
        $this->associate($ticket);

        $days = app(QueueDayService::class);
        $queue = app(QueueService::class);

        $queue->next($days->today(), 0);
        $queue->next($days->today(), 1);

        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->assertDatabaseHas('whatsapp_messages', [
            'ticket_id' => $ticket->id,
            'logical_type' => WhatsAppMessage::TYPE_APPROACHING_TURN,
            'current_number_snapshot' => 1,
            'status' => WhatsAppMessage::STATUS_PENDING,
        ]);
        Http::assertNothingSent();
    }

    public function test_il_worker_invia_il_testo_e_salva_lid_meta(): void
    {
        $message = $this->pendingMessage();

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.outbound-1']],
            ], 200),
        ]);

        app(WhatsAppSender::class)->send($message->id);
        app(WhatsAppSender::class)->send($message->id);

        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $message->id,
            'status' => WhatsAppMessage::STATUS_ACCEPTED,
            'meta_message_id' => 'wamid.outbound-1',
            'attempts' => 1,
        ]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['type'] === 'text'
            && str_contains($request['text']['body'], 'Stiamo servendo il numero 1')
            && $request['to'] === '393401112233');
    }

    public function test_un_rifiuto_meta_viene_registrato_senza_retry(): void
    {
        $message = $this->pendingMessage();

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'error' => ['code' => 131047, 'message' => 'Re-engagement message'],
            ], 400),
        ]);

        app(WhatsAppSender::class)->send($message->id);
        app(WhatsAppSender::class)->send($message->id);

        $fresh = $message->fresh();
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $fresh->status);
        $this->assertSame('meta_131047', $fresh->failure_code);
        $this->assertSame(1, $fresh->attempts);
        $this->assertSame(1, WhatsAppMessage::issueCountForQueueDay($fresh->ticket->queue_day_id));
        Http::assertSentCount(1);
    }

    public function test_delivery_webhook_aggiorna_lo_stato_senza_regressioni_o_duplicati(): void
    {
        $message = $this->pendingMessage();
        $message->forceFill([
            'status' => WhatsAppMessage::STATUS_ACCEPTED,
            'meta_message_id' => 'wamid.outbound-2',
            'accepted_at' => now(),
        ])->save();

        $delivered = $this->statusPayload('wamid.outbound-2', 'delivered', 1787265000);
        $this->postSignedWebhook($delivered)->assertOk();
        $this->postSignedWebhook($delivered)->assertOk();
        $this->postSignedWebhook($this->statusPayload('wamid.outbound-2', 'sent', 1787264900))->assertOk();

        $this->assertSame(WhatsAppMessage::STATUS_DELIVERED, $message->fresh()->status);
        $this->assertNotNull($message->fresh()->delivered_at);
        $this->assertSame(
            2,
            DB::table('whatsapp_webhook_events')->where('event_type', 'message_status')->count(),
        );
    }

    public function test_un_webhook_failed_registra_la_mancata_consegna(): void
    {
        $message = $this->pendingMessage();
        $message->forceFill([
            'status' => WhatsAppMessage::STATUS_ACCEPTED,
            'meta_message_id' => 'wamid.outbound-failed',
            'accepted_at' => now(),
        ])->save();

        $payload = $this->statusPayload('wamid.outbound-failed', 'failed', 1787265000);
        $payload['entry'][0]['changes'][0]['value']['statuses'][0]['errors'] = [['code' => 131026]];

        $this->postSignedWebhook($payload)->assertOk();

        $fresh = $message->fresh();
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $fresh->status);
        $this->assertSame('meta_131026', $fresh->failure_code);
    }

    public function test_un_ticket_gia_chiamato_non_viene_inviato(): void
    {
        $message = $this->pendingMessage();
        $days = app(QueueDayService::class);
        $queue = app(QueueService::class);

        $queue->next($days->today(), 1);
        $queue->next($days->today(), 2);

        Http::fake();
        app(WhatsAppSender::class)->send($message->id);

        $this->assertSame(WhatsAppMessage::STATUS_CANCELLED, $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_lo_stesso_numero_whatsapp_puo_associare_un_nuovo_ticket(): void
    {
        $first = $this->issue();
        $this->associate($first, '393401112233', 'wamid.first');

        app(QueueService::class)->next(app(QueueDayService::class)->today(), 0);

        $second = $this->issue();
        $this->associate($second, '393401112233', 'wamid.second');

        $this->assertTrue($first->fresh()->whatsappEnabled());
        $this->assertTrue($second->fresh()->whatsappEnabled());
        $this->assertSame('393401112233', $second->fresh()->whatsapp_recipient);
    }

    public function test_il_comando_rimuove_identificativi_scaduti(): void
    {
        $ticket = $this->issue();
        $this->associate($ticket);
        $ticket->forceFill(['whatsapp_enabled_at' => now()->subDays(3)])->save();

        $this->artisan('coda:whatsapp-recover')->assertSuccessful();

        $fresh = $ticket->fresh();
        $this->assertNull($fresh->whatsapp_recipient);
        $this->assertNull($fresh->whatsapp_enabled_at);
        $this->assertNotNull($fresh->whatsapp_identifier_purged_at);
    }

    public function test_un_worker_interrotto_non_provoca_un_secondo_invio(): void
    {
        $message = $this->pendingMessage();
        $message->forceFill([
            'status' => WhatsAppMessage::STATUS_PROCESSING,
            'processing_at' => now()->subMinutes(5),
            'attempts' => 1,
        ])->save();

        Http::fake();
        $this->artisan('coda:whatsapp-recover')->assertSuccessful();

        $fresh = $message->fresh();
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $fresh->status);
        $this->assertSame('uncertain_delivery', $fresh->failure_code);
        Http::assertNothingSent();
    }

    private function issue(): Ticket
    {
        return app(TicketService::class)->issue((string) Str::uuid());
    }

    private function associationToken(Ticket $ticket): string
    {
        $url = $this->postJson("/api/tickets/{$ticket->public_token}/whatsapp-link")
            ->assertOk()
            ->json('url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        preg_match('/CODA-([A-Za-z0-9_-]{32})/', $query['text'] ?? '', $matches);

        return $matches[1];
    }

    private function associate(
        Ticket $ticket,
        string $sender = '393401112233',
        ?string $messageId = null,
    ): void {
        $token = $this->associationToken($ticket);
        $payload = $this->inboundPayload($messageId ?? 'wamid.'.Str::uuid(), $sender, "CODA-{$token}");

        $this->postSignedWebhook($payload)->assertOk();
    }

    private function pendingMessage(): WhatsAppMessage
    {
        $this->issue();
        $this->issue();
        $ticket = $this->issue();
        $this->associate($ticket);

        app(QueueService::class)->next(app(QueueDayService::class)->today(), 0);

        return WhatsAppMessage::firstOrFail();
    }

    private function postSignedWebhook(array $payload)
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $json, self::APP_SECRET);

        return $this->call(
            'POST',
            '/webhooks/whatsapp',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature],
            content: $json,
        );
    }

    private function inboundPayload(string $messageId, string $sender, string $body): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messages' => [[
                            'id' => $messageId,
                            'from' => $sender,
                            'type' => 'text',
                            'text' => ['body' => $body],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function statusPayload(string $messageId, string $status, int $timestamp): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'statuses' => [[
                            'id' => $messageId,
                            'status' => $status,
                            'timestamp' => (string) $timestamp,
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
