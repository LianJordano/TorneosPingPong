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

    /** Descripción breve para el glosario de roles. */
    public function descripcion(): string
    {
        return match ($this) {
            self::ADMIN      => 'Control total: crea y edita torneos, jugadores, inscripciones y catálogos, carga masiva, gestiona usuarios y asigna roles. También puede cargar marcadores.',
            self::ANOTADOR   => 'Operación en cancha: carga y edita los marcadores de los partidos durante los torneos. No puede gestionar torneos, jugadores ni usuarios.',
            self::ESPECTADOR => 'Solo lectura: consulta torneos, rankings, tablas y resultados. No modifica ninguna información de la plataforma.',
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
