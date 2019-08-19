<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models\Event\CountryEvent;

use Brick\ORM\Tests\Resources\Models\Event\CountryEvent;
use Brick\ORM\Tests\Resources\Models\Country;

class EditCountryNameEvent extends CountryEvent
{
    protected string $newName;

    public function __construct(Country $country, string $newName)
    {
        parent::__construct($country);

        $this->newName = $newName;
    }

    public function getNewName() : string
    {
        return $this->newName;
    }
}
