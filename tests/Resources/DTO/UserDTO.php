<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\DTO;

class UserDTO
{
    public int $id;

    public string $name;

    public AddressDTO $address;

    public int $eventCount;
}
