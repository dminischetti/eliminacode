<?php

namespace App\Http\Controllers;

use App\Services\QueueDayService;
use Illuminate\View\View;

class QueuePageController extends Controller
{
    public function __construct(
        private readonly QueueDayService $queueDays,
    ) {
    }

    /**
     * Unica pagina del cliente. Rende lo stato iniziale server-side cosi'
     * che il primo schermo sia gia' corretto senza aspettare una chiamata,
     * e soprattutto inietta la business_date: il browser non la calcola mai
     * da solo, perche' orologio e fuso del telefono possono essere sbagliati.
     */
    public function show(): View
    {
        $day = $this->queueDays->today();

        return view('queue', [
            'boot' => [
                'today' => $day->businessDateString(),
                'business_date' => $day->businessDateString(),
                'queue_status' => $day->status,
                'current_number' => $day->current_number,
                'poll_seconds' => (int) config('queue_shop.customer_poll_seconds'),
            ],
        ]);
    }
}
