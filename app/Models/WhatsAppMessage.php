<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    public const TYPE_APPROACHING_TURN = 'approaching_turn';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'ticket_id',
        'logical_type',
        'direction',
        'status',
        'current_number_snapshot',
        'body',
        'meta_message_id',
        'attempts',
        'available_at',
        'processing_at',
        'accepted_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failure_code',
        'failure_reason',
    ];

    protected $casts = [
        'current_number_snapshot' => 'integer',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'processing_at' => 'datetime',
        'accepted_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public static function issueCountForQueueDay(int $queueDayId): int
    {
        $processingCutoff = now()->subSeconds((int) config('whatsapp.processing_timeout_seconds'));

        return self::query()
            ->whereHas('ticket', fn ($query) => $query->where('queue_day_id', $queueDayId))
            ->where(function ($query) use ($processingCutoff): void {
                $query->where('status', self::STATUS_FAILED)
                    ->orWhere(function ($pending): void {
                        $pending->where('status', self::STATUS_PENDING)
                            ->where('created_at', '<=', now()->subMinutes(2));
                    })
                    ->orWhere(function ($processing) use ($processingCutoff): void {
                        $processing->where('status', self::STATUS_PROCESSING)
                            ->where('processing_at', '<=', $processingCutoff);
                    });
            })
            ->count();
    }
}
