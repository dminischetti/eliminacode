<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TicketService;
use App\Services\WhatsAppAssociationService;
use DomainException;
use Illuminate\Http\JsonResponse;

class TicketWhatsAppController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly WhatsAppAssociationService $associations,
    ) {}

    public function store(string $token): JsonResponse
    {
        $ticket = $this->tickets->findByToken($token);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket non trovato.'], 404);
        }

        if ($ticket->whatsappEnabled()) {
            return response()->json(['status' => 'active']);
        }

        try {
            $url = $this->associations->createLink($ticket);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'status' => 'awaiting_message',
            'url' => $url,
        ]);
    }
}
