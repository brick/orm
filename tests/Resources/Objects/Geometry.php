<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Objects;

class Geometry
{
    private string $wkt;

    private int $srid;

    public function __construct(string $wkt, int $srid)
    {
        $this->wkt  = $wkt;
        $this->srid = $srid;
    }

    public function getWKT() : string
    {
        return $this->wkt;
    }

    public function getSRID() : int
    {
        return $this->srid;
    }
}
