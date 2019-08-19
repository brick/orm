<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models;

/**
 * A class featuring inheritance mapping.
 */
abstract class Event
{
    protected int $id;

    protected int $time;

    public function __construct()
    {
        $this->time = 1234567890; // hardcoded for tests
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getTime() : int
    {
        return $this->time;
    }
}
