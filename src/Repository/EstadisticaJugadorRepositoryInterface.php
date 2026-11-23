<?php

declare(strict_types=1);

namespace PingPong\Repository;

interface EstadisticaJugadorRepositoryInterface
{
    /** @return list<array<string,mixed>> */
    public function personas(): array;

    /** @return list<array<string,mixed>> */
    public function torneosFinalizados(): array;

    /** @return list<array<string,mixed>> */
    public function inscripcionesFinalizadas(): array;

    /** @return list<array<string,mixed>> */
    public function partidosFinalizadosReales(): array;

    /** @return list<array{a:int,b:int,ganador:?int,setsA:int,setsB:int,puntosA:int,puntosB:int}> */
    public function ligaAgregada(int $torneoId, string $origen = 'TORNEO'): array;

    /** @return list<array{id:int,nombre:string}> */
    public function jugadoresTorneo(int $torneoId, string $origen = 'TORNEO'): array;

    /** @return list<array{a:?int,b:?int,ganador:?int,ronda:int,estado:string}> */
    public function bracketPodio(int $torneoId, string $origen = 'TORNEO'): array;
}
