<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Objects;

class Geometry
{
    /**
     * @var string
     */
    private $wkt;

    /**
     * @var int
     */
    private $srid;

    /**
     * @param string $wkt
     * @param int    $srid
     */
    public function __construct(string $wkt, int $srid)
    {
        $this->wkt  = $wkt;
        $this->srid = $srid;
    }

    /**
     * @return string
     */
    public function getWKT(): string
    {
        return $this->wkt;
    }

    /**
     * @return int
     */
    public function getSRID(): int
    {
        return $this->srid;
    }
}
