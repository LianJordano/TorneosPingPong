<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Cómo se forman las parejas en un torneo de dobles. Irrelevante en individual.
 *
 *   MANUAL    → al inscribir se eligen las dos personas de la pareja.
 *   ALEATORIO → se inscriben personas sueltas y el sistema sortea las parejas
 *               al cerrar la inscripción (ver {@see SorteoParejas}).
 */
enum Emparejamiento: string
{
    case MANUAL = 'MANUAL';
    case ALEATORIO = 'ALEATORIO';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL    => 'Manual (elegidas al inscribir)',
            self::ALEATORIO => 'Aleatorio (sorteadas por el sistema)',
        };
    }

    public function esAleatorio(): bool
    {
        return $this === self::ALEATORIO;
    }
}
