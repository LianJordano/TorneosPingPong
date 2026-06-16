<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Modalidad de juego del torneo.
 *
 *   INDIVIDUAL → cada inscripción representa a una persona.
 *   DOBLES     → cada inscripción representa a una pareja (dos personas).
 *
 * La modalidad es un atributo del torneo: un torneo es enteramente individual
 * o enteramente de dobles. Toda la maquinaria de fixture, bracket, tabla y
 * podio opera sobre la inscripción (no sobre la persona), por lo que el cambio
 * de modalidad no altera esos cálculos: solo cambia qué representa cada
 * inscripción y cómo se la etiqueta.
 */
enum Modalidad: string
{
    case INDIVIDUAL = 'INDIVIDUAL';
    case DOBLES = 'DOBLES';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Individual',
            self::DOBLES     => 'Dobles',
        };
    }

    public function esDobles(): bool
    {
        return $this === self::DOBLES;
    }
}
