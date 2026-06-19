<?php

declare(strict_types=1);

namespace PingPong\Service;

use PingPong\Domain\EmailEspectador;
use PingPong\Repository\PersonaRepository;
use PingPong\Repository\UsuarioRepository;

/**
 * Convierte jugadores (persona) en usuarios de la plataforma con rol ESPECTADOR.
 *
 * La contraseña inicial es compartida y conocida ({@see self::PASSWORD_INICIAL});
 * el espectador puede cambiarla luego desde "Mi cuenta". El email de login se
 * resuelve con {@see EmailEspectador}: el real del jugador si sirve y está libre,
 * o uno sintético derivado del id.
 */
final class JugadorUsuarioService
{
    public const PASSWORD_INICIAL = 'Jugador123';

    public function __construct(
        private PersonaRepository $personas,
        private UsuarioRepository $usuarios,
    ) {
    }

    /**
     * Convierte una persona. Idempotente: si ya tiene usuario, no duplica.
     *
     * @return array{status:string, mensaje:string, email:?string}
     *         status ∈ {creado, ya_existe, no_existe, email_ocupado}
     */
    public function convertir(int $personaId): array
    {
        $persona = $this->personas->find($personaId);
        if ($persona === null) {
            return ['status' => 'no_existe', 'mensaje' => 'El jugador no existe.', 'email' => null];
        }

        if ($this->usuarios->findByPersonaId($personaId) !== null) {
            return [
                'status'  => 'ya_existe',
                'mensaje' => $this->nombreCompleto($persona) . ' ya tiene un usuario.',
                'email'   => null,
            ];
        }

        $emailReal   = strtolower(trim((string) ($persona['email'] ?? '')));
        $disponible  = $emailReal !== '' && !$this->usuarios->emailEnUso($emailReal);
        $email       = EmailEspectador::generar($personaId, $persona['email'] ?? null, $disponible);

        // Salvaguarda ante el caso límite de que el email final ya exista
        // (evita reventar contra la clave única con una excepción de PDO).
        if ($this->usuarios->emailEnUso($email)) {
            return [
                'status'  => 'email_ocupado',
                'mensaje' => 'El email ' . $email . ' ya está en uso por otro usuario.',
                'email'   => $email,
            ];
        }

        $this->usuarios->crearEspectador(
            $personaId,
            $this->nombreCompleto($persona),
            $email,
            password_hash(self::PASSWORD_INICIAL, PASSWORD_DEFAULT),
        );

        return [
            'status'  => 'creado',
            'mensaje' => $this->nombreCompleto($persona) . ' ahora puede ingresar con el email ' . $email . '.',
            'email'   => $email,
        ];
    }

    /**
     * Convierte varios jugadores de una sola pasada.
     *
     * @param list<int> $personaIds
     * @return array{creados:int, yaExistian:int, omitidos:int, detalles:list<array{personaId:int, status:string, email:?string}>}
     */
    public function convertirLote(array $personaIds): array
    {
        $creados = 0;
        $yaExistian = 0;
        $omitidos = 0;
        $detalles = [];

        // Únicos y positivos: evita reprocesar el mismo id si llega repetido.
        $ids = array_values(array_unique(array_filter($personaIds, static fn (int $id): bool => $id > 0)));

        foreach ($ids as $personaId) {
            $resultado = $this->convertir($personaId);
            match ($resultado['status']) {
                'creado'    => $creados++,
                'ya_existe' => $yaExistian++,
                default     => $omitidos++,
            };
            $detalles[] = [
                'personaId' => $personaId,
                'status'    => $resultado['status'],
                'email'     => $resultado['email'],
            ];
        }

        return [
            'creados'    => $creados,
            'yaExistian' => $yaExistian,
            'omitidos'   => $omitidos,
            'detalles'   => $detalles,
        ];
    }

    /** @param array<string,mixed> $persona */
    private function nombreCompleto(array $persona): string
    {
        return trim((string) ($persona['nombre'] ?? '') . ' ' . (string) ($persona['apellido'] ?? ''));
    }
}
