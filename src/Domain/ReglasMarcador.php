<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Reglas de validación de marcadores de tenis de mesa.
 *
 * - Un SET se gana al llegar a los puntos configurados (default 11) con una
 *   diferencia mínima (default 2). En empate al límite (10-10) se sigue hasta
 *   sacar esa diferencia: 12-10, 13-11, etc.
 * - Un PARTIDO se gana al ganar la mayoría de los sets según el formato
 *   (mejor de N → (N+1)/2 sets).
 */
final class ReglasMarcador
{
    public function __construct(
        public readonly int $puntosPorSet = 11,
        public readonly int $diferenciaMinima = 2,
        public readonly int $formatoSets = 3,
    ) {
    }

    /** Sets necesarios para ganar el partido (mejor de N). */
    public function setsParaGanar(): int
    {
        return intdiv($this->formatoSets, 2) + 1;
    }

    /** ¿Es un set válido y completo (con ganador)? */
    public function setValido(int $a, int $b): bool
    {
        if ($a < 0 || $b < 0 || $a === $b) {
            return false;
        }
        $ganador = max($a, $b);
        $perdedor = min($a, $b);
        $dif = $ganador - $perdedor;

        // Debe alcanzar los puntos mínimos, con la diferencia mínima, y
        // una vez en deuce solo se puede ganar por exactamente esa diferencia.
        return $ganador >= $this->puntosPorSet
            && $dif >= $this->diferenciaMinima
            && ($ganador === $this->puntosPorSet || $dif === $this->diferenciaMinima);
    }

    /**
     * Valida la secuencia completa de sets de un partido NORMAL.
     *
     * @param list<array{0:int,1:int}> $sets pares [puntosA, puntosB] en orden
     * @return array{valido:bool, errores:list<string>, ganador:?string, setsA:int, setsB:int}
     *         ganador: 'A' | 'B' | null
     */
    public function validarPartido(array $sets): array
    {
        $errores = [];
        $setsA = 0;
        $setsB = 0;
        $ganador = null;
        $necesarios = $this->setsParaGanar();
        $total = count($sets);

        if ($total === 0) {
            return ['valido' => false, 'errores' => ['Cargá al menos un set.'], 'ganador' => null, 'setsA' => 0, 'setsB' => 0];
        }
        if ($total > $this->formatoSets) {
            $errores[] = "No puede haber más de {$this->formatoSets} sets en este formato.";
        }

        foreach ($sets as $idx => [$a, $b]) {
            $numero = $idx + 1;
            if (!$this->setValido($a, $b)) {
                $errores[] = "Set {$numero} inválido ({$a}-{$b}): revisá puntos y diferencia.";
                continue;
            }
            if ($a > $b) {
                $setsA++;
            } else {
                $setsB++;
            }
            // Una vez definido el partido, no puede haber más sets.
            if (($setsA === $necesarios || $setsB === $necesarios) && $numero < $total) {
                $errores[] = "El partido ya estaba definido en el set {$numero}: sobran sets.";
                break;
            }
        }

        if ($errores === []) {
            if ($setsA >= $necesarios) {
                $ganador = 'A';
            } elseif ($setsB >= $necesarios) {
                $ganador = 'B';
            } else {
                $errores[] = "El partido no está definido: nadie alcanzó {$necesarios} sets.";
            }
        }

        return [
            'valido'  => $errores === [],
            'errores' => $errores,
            'ganador' => $errores === [] ? $ganador : null,
            'setsA'   => $setsA,
            'setsB'   => $setsB,
        ];
    }

    /**
     * Valida sets sueltos (sin exigir que definan el partido), p. ej. los
     * sets ya jugados antes de un retiro. Devuelve los números de set inválidos.
     *
     * @param list<array{0:int,1:int}> $sets
     * @return list<int>
     */
    public function setsInvalidos(array $sets): array
    {
        $invalidos = [];
        foreach ($sets as $idx => [$a, $b]) {
            if (!$this->setValido($a, $b)) {
                $invalidos[] = $idx + 1;
            }
        }
        return $invalidos;
    }

    /**
     * Resumen agregado de una lista de sets (para la tabla de posiciones).
     *
     * @param list<array{0:int,1:int}> $sets
     * @return array{setsA:int,setsB:int,puntosA:int,puntosB:int}
     */
    public static function resumen(array $sets): array
    {
        $setsA = $setsB = $puntosA = $puntosB = 0;
        foreach ($sets as [$a, $b]) {
            $puntosA += $a;
            $puntosB += $b;
            if ($a > $b) {
                $setsA++;
            } elseif ($b > $a) {
                $setsB++;
            }
        }
        return ['setsA' => $setsA, 'setsB' => $setsB, 'puntosA' => $puntosA, 'puntosB' => $puntosB];
    }
}
