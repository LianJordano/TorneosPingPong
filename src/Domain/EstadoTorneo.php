<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Ciclo de vida del torneo como máquina de estados.
 *
 * Las transiciones válidas viven acá (no en los controladores), de modo que
 * la regla de negocio sea única, testeable y no se pueda saltar desde la UI.
 *
 *   BORRADOR → INSCRIPCION_ABIERTA ⇄ INSCRIPCION_CERRADA → FASE_LIGA
 *            → FASE_ELIMINATORIA → FINALIZADO        (y → CANCELADO)
 */
enum EstadoTorneo: string
{
    case BORRADOR = 'BORRADOR';
    case INSCRIPCION_ABIERTA = 'INSCRIPCION_ABIERTA';
    case INSCRIPCION_CERRADA = 'INSCRIPCION_CERRADA';
    case FASE_LIGA = 'FASE_LIGA';
    case FASE_ELIMINATORIA = 'FASE_ELIMINATORIA';
    case FINALIZADO = 'FINALIZADO';
    case CANCELADO = 'CANCELADO';

    public function label(): string
    {
        return match ($this) {
            self::BORRADOR            => 'Borrador',
            self::INSCRIPCION_ABIERTA => 'Inscripción abierta',
            self::INSCRIPCION_CERRADA => 'Inscripción cerrada',
            self::FASE_LIGA           => 'Fase de liga',
            self::FASE_ELIMINATORIA   => 'Fase eliminatoria',
            self::FINALIZADO          => 'Finalizado',
            self::CANCELADO           => 'Cancelado',
        };
    }

    /** Clase CSS para el badge de estado. */
    public function badge(): string
    {
        return match ($this) {
            self::BORRADOR            => 'badge--gray',
            self::INSCRIPCION_ABIERTA => 'badge--green',
            self::INSCRIPCION_CERRADA => 'badge--amber',
            self::FASE_LIGA           => 'badge--blue',
            self::FASE_ELIMINATORIA   => 'badge--purple',
            self::FINALIZADO          => 'badge--dark',
            self::CANCELADO           => 'badge--red',
        };
    }

    /**
     * Transiciones posibles según la máquina de estados completa.
     *
     * @return list<self>
     */
    public function siguientesPosibles(): array
    {
        return match ($this) {
            self::BORRADOR            => [self::INSCRIPCION_ABIERTA, self::CANCELADO],
            self::INSCRIPCION_ABIERTA => [self::INSCRIPCION_CERRADA, self::CANCELADO],
            self::INSCRIPCION_CERRADA => [self::INSCRIPCION_ABIERTA, self::FASE_LIGA, self::CANCELADO],
            self::FASE_LIGA           => [self::FASE_ELIMINATORIA, self::CANCELADO],
            self::FASE_ELIMINATORIA   => [self::FINALIZADO, self::CANCELADO],
            self::FINALIZADO          => [],
            self::CANCELADO           => [],
        };
    }

    public function puedeTransicionarA(self $destino): bool
    {
        return in_array($destino, $this->siguientesPosibles(), true);
    }

    /** ¿Se puede editar la configuración del torneo en este estado? */
    public function permiteEditarConfig(): bool
    {
        return $this === self::BORRADOR || $this === self::INSCRIPCION_ABIERTA;
    }

    /** ¿Se pueden inscribir / desinscribir jugadores en este estado? */
    public function permiteInscripciones(): bool
    {
        return $this === self::INSCRIPCION_ABIERTA;
    }

    /** ¿Es un estado terminal (no admite más transiciones)? */
    public function esTerminal(): bool
    {
        return $this->siguientesPosibles() === [];
    }
}
