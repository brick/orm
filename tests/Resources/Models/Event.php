<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models;

/**
 * A class featuring inheritance mapping.
 */
abstract class Event
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var int
     */
    protected $time;

    /**
     * Event constructor.
     */
    public function __construct()
    {
        $this->time = 1234567890; // hardcoded for tests
    }

    /**
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getTime() : int
    {
        return $this->time;
    }
}
