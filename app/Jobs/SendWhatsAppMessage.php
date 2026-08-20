<?php

namespace App\Jobs;

use App\Services\WhatsAppSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 20;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $messageId) {}

    public function handle(WhatsAppSender $sender): void
    {
        $sender->send($this->messageId);
    }
}
