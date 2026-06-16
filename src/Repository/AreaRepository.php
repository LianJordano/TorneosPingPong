<?php

declare(strict_types=1);

namespace PingPong\Repository;

final class AreaRepository extends CatalogoRepository
{
    protected function tabla(): string
    {
        return 'area';
    }

    protected function columnaPersona(): string
    {
        return 'area_id';
    }
}
