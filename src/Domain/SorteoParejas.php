<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Sorteo de parejas para torneos de dobles con emparejamiento ALEATORIO.
 *
 * Recibe la lista de inscripciones individuales (una persona cada una) y las
 * agrupa de a dos al azar. Si la cantidad es impar, queda un "sobrante" que el
 * llamador debe resolver (avisar al usuario y/o dejar sin pareja).
 *
 * El barajado se inyecta para que la lógica sea determinista en los tests: con
 * el barajador identidad las parejas salen en el orden recibido.
 */
final class SorteoParejas
{
    /**
     * @param list<int> $ids ids de inscripción (individuales)
     * @param (callable(list<int>):list<int>)|null $barajador
     * @return array{parejas:list<array{0:int,1:int}>,sobrante:?int}
     */
    public static function emparejar(array $ids, ?callable $barajador = null): array
    {
        $ids = array_values($ids);

        $barajador ??= static function (array $xs): array {
            shuffle($xs);
            return $xs;
        };
        $ids = array_values($barajador($ids));

        $parejas = [];
        $total = count($ids);
        for ($i = 0; $i + 1 < $total; $i += 2) {
            $parejas[] = [$ids[$i], $ids[$i + 1]];
        }

        $sobrante = ($total % 2 !== 0) ? $ids[$total - 1] : null;

        return ['parejas' => $parejas, 'sobrante' => $sobrante];
    }
}
