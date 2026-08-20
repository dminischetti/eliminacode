<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub.mode', $request->query('hub_mode'));
        $token = $request->query('hub.verify_token', $request->query('hub_verify_token'));
        $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));

        if ($mode !== 'subscribe'
            || ! is_string($token)
            || ! is_string($challenge)
            || ! filled(config('whatsapp.verify_token'))
            || ! hash_equals((string) config('whatsapp.verify_token'), $token)) {
            return response('Verifica webhook non valida.', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsAppWebhookService $webhooks): Response
    {
        $rawBody = $request->getContent();
        $provided = (string) $request->header('X-Hub-Signature-256', '');
        $appSecret = (string) config('whatsapp.app_secret');
        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $appSecret);

        if ($appSecret === '' || ! hash_equals($expected, $provided)) {
            return response('Firma webhook non valida.', 403);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response('Payload non valido.', 400);
        }

        $webhooks->process($payload);

        return response('EVENT_RECEIVED', 200)->header('Content-Type', 'text/plain');
    }
}
