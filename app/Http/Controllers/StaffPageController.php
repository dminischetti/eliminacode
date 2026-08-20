<?php

namespace App\Http\Controllers;

use App\Services\QueueDayService;
use Illuminate\View\View;

class StaffPageController extends Controller
{
    public function __construct(
        private readonly QueueDayService $queueDays,
    ) {}

    public function show(): View
    {
        $day = $this->queueDays->today();

        return view('staff', [
            'boot' => [
                'business_date' => $day->businessDateString(),
                'queue_status' => $day->status,
                'current_number' => $day->current_number,
                'last_issued_number' => $day->last_issued_number,
                'has_waiting' => $day->hasWaitingNumbers(),
                'poll_seconds' => (int) config('queue_shop.staff_poll_seconds'),
            ],
        ]);
    }
}
