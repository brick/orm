<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models\Event;

use Brick\ORM\Tests\Resources\Models\Event;
use Brick\ORM\Tests\Resources\Models\Country;

abstract class CountryEvent extends Event
{
    protected Country $country;

    public function __construct(Country $country)
    {
        parent::__construct();

        $this->country = $country;
    }

    public function getCountry() : Country
    {
        return $this->country;
    }
}
