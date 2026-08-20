<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueDay extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'business_date',
        'status',
        'current_number',
        'last_issued_number',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'business_date' => 'date',
        'current_number' => 'integer',
        'last_issued_number' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** §43 - se falso, PROSSIMO non ha nulla da chiamare. */
    public function hasWaitingNumbers(): bool
    {
        return $this->current_number < $this->last_issued_number;
    }

    /** Data della giornata nel formato usato dal client per il confronto (§20/§21). */
    public function businessDateString(): string
    {
        return $this->business_date->toDateString();
    }
}
