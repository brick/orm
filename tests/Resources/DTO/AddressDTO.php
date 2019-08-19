<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\DTO;

class AddressDTO
{
    public string $street;

    public string $city;

    public ?string $postcode;

    public string $countryCode;
}
