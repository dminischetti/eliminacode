<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\QueueException;
use App\Http\Controllers\Controller;
use App\Models\QueueDay;
use App\Services\QueueDayService;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffQueueController extends Controller
{
    public function __construct(
        private readonly QueueDayService $queueDays,
        private readonly QueueService $queue,
    ) {
    }

    public function show(): JsonResponse
    {
        return response()->json($this->payload($this->queueDays->today()));
    }

    /**
     * §40-§42 - PROSSIMO.
     *
     * expected_current_number e' il numero che lo staff aveva davanti agli
     * occhi quando ha toccato il pulsante. Se nel frattempo e' cambiato,
     * l'avanzamento viene rifiutato: e' cosi' che un doppio tap non fa
     * sparire un cliente.
     *
     * Il 409 riporta anche lo stato aggiornato, cosi' la UI si risincronizza
     * senza una seconda chiamata.
     */
    public function next(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expected_current_number' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $day = $this->queue->next(
                $this->queueDays->today(),
                (int) $data['expected_current_number']
            );
        } catch (QueueException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'queue' => $this->payload($this->queueDays->today()),
            ], $e->httpStatus());
        }

        return response()->json($this->payload($day));
    }

    /** §44-§46 */
    public function correct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'new_current_number' => ['required', 'integer', 'min:0'],
            'expected_current_number' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $day = $this->queue->correct(
                $this->queueDays->today(),
                (int) $data['new_current_number'],
                (int) $data['expected_current_number'],
                $request->user()?->id
            );
        } catch (QueueException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'queue' => $this->payload($this->queueDays->today()),
            ], $e->httpStatus());
        }

        return response()->json($this->payload($day));
    }

    /** §12 */
    public function close(): JsonResponse
    {
        $day = $this->queueDays->close($this->queueDays->today());

        return response()->json($this->payload($day));
    }

    /** §13 - i contatori restano dov'erano. */
    public function reopen(): JsonResponse
    {
        $day = $this->queueDays->reopen($this->queueDays->today());

        return response()->json($this->payload($day));
    }

    private function payload(QueueDay $day): array
    {
        return [
            'business_date' => $day->businessDateString(),
            'queue_status' => $day->status,
            'current_number' => $day->current_number,
            'last_issued_number' => $day->last_issued_number,
            'has_waiting' => $day->hasWaitingNumbers(),
        ];
    }
}
