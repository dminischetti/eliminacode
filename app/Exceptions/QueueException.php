<?php

namespace App\Exceptions;

use RuntimeException;

class QueueException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus)
    {
        parent::__construct($message);
    }

    public static function closed(): self
    {
        return new self('La coda di oggi e\' chiusa.', 409);
    }

    /**
     * 409 - lo staff ha agito su uno stato ormai vecchio: doppio tap,
     * secondo dispositivo, richiesta duplicata (§42). La UI ricarica.
     */
    public static function staleState(): self
    {
        return new self('La coda e\' cambiata. Aggiornamento in corso.', 409);
    }

    /** 409 - PROSSIMO a coda vuota (§43). */
    public static function noWaitingNumbers(): self
    {
        return new self('Nessun numero in attesa.', 409);
    }

    /** 422 - correzione fuori dai limiti (§45). */
    public static function correctionOutOfRange(int $value, int $lastIssued): self
    {
        return new self(sprintf(
            'Il numero %d e\' fuori dai limiti consentiti (0-%d).',
            $value,
            $lastIssued
        ), 422);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
