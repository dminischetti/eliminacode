<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un solo tipo di eccezione di dominio. Il codice HTTP suggerito e' indicato
 * accanto a ciascun costruttore: e' il controller a fare la traduzione.
 */
class QueueException extends RuntimeException
{
    /** 409 - la giornata e' chiusa (§12). */
    public static function closed(): self
    {
        return new self('La coda di oggi e\' chiusa.');
    }

    /**
     * 409 - lo staff ha agito su uno stato ormai vecchio: doppio tap,
     * secondo dispositivo, richiesta duplicata (§42). La UI ricarica.
     */
    public static function staleState(): self
    {
        return new self('La coda e\' cambiata. Aggiornamento in corso.');
    }

    /** 409 - PROSSIMO a coda vuota (§43). */
    public static function noWaitingNumbers(): self
    {
        return new self('Nessun numero in attesa.');
    }

    /** 422 - correzione fuori dai limiti (§45). */
    public static function correctionOutOfRange(int $value, int $lastIssued): self
    {
        return new self(sprintf(
            'Il numero %d e\' fuori dai limiti consentiti (0-%d).',
            $value,
            $lastIssued
        ));
    }
}
