<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Tipo de resultado de un partido y la lógica de qué exige cada uno.
 */
enum TipoResultado: string
{
    case NORMAL = 'NORMAL';
    case WALKOVER = 'WALKOVER';
    case RETIRO = 'RETIRO';
    case DESCALIFICACION = 'DESCALIFICACION';
    case NO_PRESENTADO = 'NO_PRESENTADO';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL          => 'Normal',
            self::WALKOVER        => 'Walkover (W.O.)',
            self::RETIRO          => 'Retiro / abandono',
            self::DESCALIFICACION => 'Descalificación',
            self::NO_PRESENTADO   => 'No presentado',
        };
    }

    /** Solo NORMAL exige una secuencia de sets que defina el partido. */
    public function requiereSetsCompletos(): bool
    {
        return $this === self::NORMAL;
    }

    /** NORMAL y RETIRO admiten sets cargados (en retiro, los ya jugados). */
    public function permiteSets(): bool
    {
        return $this === self::NORMAL || $this === self::RETIRO;
    }

    /** En todo lo que no es NORMAL hay que indicar el ganador a mano. */
    public function requiereGanadorManual(): bool
    {
        return $this !== self::NORMAL;
    }

    /** @return list<self> */
    public static function casos(): array
    {
        return self::cases();
    }
}
