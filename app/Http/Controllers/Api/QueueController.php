<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QueueDayService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint aggiuntivo rispetto all'elenco della specifica: serve alla
 * homepage per tenere aggiornato "ORA SERVIAMO" quando il browser non ha
 * ancora un ticket. Senza, il numero servito resterebbe fermo a quello
 * del caricamento pagina.
 */
class QueueController extends Controller
{
    public function __construct(
        private readonly QueueDayService $queueDays,
    ) {}

    public function show(): JsonResponse
    {
        $day = $this->queueDays->today();

        return response()->json([
            'today' => $day->businessDateString(),
            'business_date' => $day->businessDateString(),
            'queue_status' => $day->status,
            'current_number' => $day->current_number,
        ]);
    }
}
