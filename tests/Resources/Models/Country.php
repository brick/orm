<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models;

/**
 * A class with a string identity, non-autoincrement.
 */
class Country
{
    protected string $code;

    protected string $name;

    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
    }

    public function getCode() : string
    {
        return $this->code;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function setName(string $name) : void
    {
        $this->name = $name;
    }
}
