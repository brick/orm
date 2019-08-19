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
        $this->address  = $address;
        $this->location = $location;
    }

    /**
     * @return Address
     */
    public function getAddress() : Address
    {
        return $this->address;
    }

    /**
     * @return Geometry
     */
    public function getLocation() : Geometry
    {
        return $this->location;
    }
}
