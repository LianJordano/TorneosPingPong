<?php

declare(strict_types=1);

namespace PingPong\Repository;

final class NivelRepository extends CatalogoRepository
{
    protected function tabla(): string
    {
        return 'nivel';
    }

    protected function columnaPersona(): string
    {
        return 'nivel_id';
    }
}
