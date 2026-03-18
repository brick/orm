<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models;

use Brick\ORM\Tests\Resources\Objects\Geometry;

/**
 * An embeddable class, composed of another embeddable, and a custom type.
 */
class GeoAddress
{
    protected Address $address;

    protected Geometry $location;

    public function __construct(Address $address, Geometry $location)
    {
        $this->address = $address;
        $this->location = $location;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getLocation(): Geometry
    {
        return $this->location;
    }
}
