<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Roles del sistema y sus permisos base.
 */
enum Rol: string
{
    case ADMIN = 'ADMIN';
    case ANOTADOR = 'ANOTADOR';
    case ESPECTADOR = 'ESPECTADOR';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN      => 'Administrador',
            self::ANOTADOR   => 'Anotador',
            self::ESPECTADOR => 'Espectador',
        };
    }

    /** Puede crear/editar/eliminar torneos, personas e inscripciones. */
    public function puedeGestionar(): bool
    {
        return $this === self::ADMIN;
    }

    /** Puede cargar y editar marcadores (se usa a partir de la Fase 2). */
    public function puedeAnotar(): bool
    {
        return $this === self::ADMIN || $this === self::ANOTADOR;
    }
}
