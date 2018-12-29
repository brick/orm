<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models\Event\CountryEvent;

use Brick\ORM\Tests\Resources\Models\Event\CountryEvent;
use Brick\ORM\Tests\Resources\Models\Country;

class EditCountryNameEvent extends CountryEvent
{
    /**
     * @var string
     */
    protected $newName;

    /**
     * @param Country   $country
     * @param string $newName
     */
    public function __construct(Country $country, string $newName)
    {
        parent::__construct($country);

        $this->newName = $newName;
    }

    /**
     * @return string
     */
    public function getNewName() : string
    {
        return $this->newName;
    }
}
