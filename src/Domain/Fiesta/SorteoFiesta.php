<?php

declare(strict_types=1);

namespace PingPong\Domain\Fiesta;

use PingPong\Domain\BracketEliminacion;

/**
 * Party-tournament draw. Everything is random and the result is returned as a
 * "script" (guion): the ordered list of reveal steps the roulette plays back.
 *
 * Doubles: participants are shuffled; the first half are revealed one by one as
 * the first member of each team ("capitan"), then the roulette spins again for
 * each team and reveals its partner ("companero").
 *
 * Then teams are shuffled again and placed one by one:
 *   - ELIMINACION: into a first-round bracket slot ("llave").
 *   - GRUPOS: into a group, dealt round-robin so group sizes differ by at most 1 ("grupo").
 *
 * Teams are identified by their reveal number (1..N) because database ids do
 * not exist yet; the repository maps numbers to ids when persisting.
 * The shuffler is injectable so tests are deterministic.
 */
final class SorteoFiesta
{
    public const ELIMINACION = 'ELIMINACION';
    public const GRUPOS = 'GRUPOS';

    /**
     * @param list<int> $participantes participant ids
     * @param (callable(list<int>):list<int>)|null $barajador
     * @return array{
     *     equipos: list<array{numero:int,p1:int,p2:?int}>,
     *     orden: list<int>,
     *     grupos: array<int,int>,
     *     bracket: array{tamano:int,rondas:int,partidos:list<array<string,mixed>>},
     *     guion: list<array<string,int|string>>
     * }
     */
    public static function sortear(
        array $participantes,
        bool $dobles,
        string $formato,
        int $numGrupos = 1,
        ?callable $barajador = null,
    ): array {
        $participantes = array_values($participantes);
        $barajador ??= static function (array $xs): array {
            shuffle($xs);
            return $xs;
        };

        self::validar(count($participantes), $dobles, $formato, $numGrupos);

        $mezclados = array_values($barajador($participantes));
        $guion = [];
        $equipos = [];

        if ($dobles) {
            $mitad = intdiv(count($mezclados), 2);
            for ($k = 0; $k < $mitad; $k++) {
                $equipos[] = ['numero' => $k + 1, 'p1' => $mezclados[$k], 'p2' => $mezclados[$mitad + $k]];
                $guion[] = ['tipo' => 'capitan', 'equipo' => $k + 1, 'participante' => $mezclados[$k]];
            }
            for ($k = 0; $k < $mitad; $k++) {
                $guion[] = ['tipo' => 'companero', 'equipo' => $k + 1, 'participante' => $mezclados[$mitad + $k]];
            }
        } else {
            foreach ($mezclados as $k => $id) {
                $equipos[] = ['numero' => $k + 1, 'p1' => $id, 'p2' => null];
            }
        }

        $numeros = array_map(static fn (array $e): int => $e['numero'], $equipos);
        $orden = array_values($barajador($numeros));

        $grupos = [];
        $bracket = ['tamano' => 0, 'rondas' => 0, 'partidos' => []];

        if ($formato === self::GRUPOS) {
            foreach ($orden as $i => $numero) {
                $grupo = ($i % $numGrupos) + 1;
                $grupos[$numero] = $grupo;
                $guion[] = ['tipo' => 'grupo', 'equipo' => $numero, 'grupo' => $grupo];
            }
        } else {
            $bracket = BracketEliminacion::generar($orden);
            $slot = [];
            foreach ($bracket['partidos'] as $p) {
                if ($p['ronda'] !== 1) {
                    continue;
                }
                foreach (['a', 'b'] as $lado) {
                    if ($p[$lado] !== null) {
                        $slot[$p[$lado]] = ['partido' => $p['orden'], 'lado' => $lado];
                    }
                }
            }
            foreach ($orden as $numero) {
                $guion[] = [
                    'tipo'    => 'llave',
                    'equipo'  => $numero,
                    'partido' => $slot[$numero]['partido'],
                    'lado'    => $slot[$numero]['lado'],
                ];
            }
        }

        return [
            'equipos' => $equipos,
            'orden'   => $orden,
            'grupos'  => $grupos,
            'bracket' => $bracket,
            'guion'   => $guion,
        ];
    }

    /** Teams that the given participant count produces. */
    public static function cantidadEquipos(int $participantes, bool $dobles): int
    {
        return $dobles ? intdiv($participantes, 2) : $participantes;
    }

    /**
     * Throws a user-facing message when the draw cannot be made.
     *
     * @throws \DomainException
     */
    public static function validar(int $participantes, bool $dobles, string $formato, int $numGrupos): void
    {
        if (!in_array($formato, [self::ELIMINACION, self::GRUPOS], true)) {
            throw new \DomainException('Formato de torneo inválido.');
        }
        if ($dobles && $participantes % 2 !== 0) {
            throw new \DomainException("Para dobles se necesita un número par de participantes (hay {$participantes}). Sumá o quitá a alguien.");
        }
        $equipos = self::cantidadEquipos($participantes, $dobles);
        if ($equipos < 2) {
            $minimo = $dobles ? 4 : 2;
            throw new \DomainException("Se necesitan al menos {$minimo} participantes para armar el torneo.");
        }
        if ($formato === self::GRUPOS) {
            if ($numGrupos < 1) {
                throw new \DomainException('Indicá al menos 1 grupo.');
            }
            if ($equipos < $numGrupos * 2) {
                throw new \DomainException("Con {$equipos} equipos no alcanza para {$numGrupos} grupos: cada grupo necesita al menos 2.");
            }
        }
    }
}
