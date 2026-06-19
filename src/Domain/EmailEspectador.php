<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Decide el email de login de un jugador al convertirlo en usuario espectador.
 *
 * Regla: si el jugador tiene un email real, válido y todavía libre, se usa ese
 * (normalizado). Si no tiene, es inválido o ya está ocupado por otro usuario,
 * se genera uno sintético derivado del id de la persona, que es único.
 */
final class EmailEspectador
{
    public const DOMINIO_SINTETICO = 'pingpong.local';

    public static function generar(int $personaId, ?string $emailPersona, bool $emailDisponible): string
    {
        $email = strtolower(trim((string) $emailPersona));

        if ($email !== '' && $emailDisponible && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            return $email;
        }

        return 'jugador' . $personaId . '@' . self::DOMINIO_SINTETICO;
    }
}
