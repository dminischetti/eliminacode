<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    public const STATE_WAITING = 'waiting';
    public const STATE_CALLED = 'called';
    public const STATE_PASSED = 'passed';

    protected $fillable = [
        'queue_day_id',
        'number',
        'public_token',
        'idempotency_key',
    ];

    /** §31 - non devono mai finire in una risposta API. */
    protected $hidden = [
        'idempotency_key',
    ];

    protected $casts = [
        'number' => 'integer',
    ];

    public function queueDay(): BelongsTo
    {
        return $this->belongsTo(QueueDay::class);
    }

    /**
     * §15 - Lo stato del ticket non e' una colonna: si ricava dal confronto
     * con current_number. Se la giornata e' closed, e' la UI a far prevalere
     * lo stato di chiusura (§34).
     */
    public function state(int $currentNumber): string
    {
        return match (true) {
            $this->number > $currentNumber => self::STATE_WAITING,
            $this->number === $currentNumber => self::STATE_CALLED,
            default => self::STATE_PASSED,
        };
    }

    /** §29 - "Mancano N numeri", mai "N persone" (§30). */
    public function remaining(int $currentNumber): int
    {
        return max(0, $this->number - $currentNumber - 1);
    }

}
