<?php

declare(strict_types=1);

namespace Brick\ORM;

use InvalidArgumentException;

class QueryOrderBy
{
    private string $property;

    /**
     * @var 'ASC'|'DESC'
     */
    private string $direction;

    /**
     * QueryOrderBy constructor.
     *
     * @param string $property  The property name.
     * @param string $direction The order direction, 'ASC' or 'DESC'.
     *
     * @throws InvalidArgumentException If the order direction is invalid.
     */
    public function __construct(string $property, string $direction)
    {
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new InvalidArgumentException('Invalid order by direction.');
        }

        $this->property = $property;
        $this->direction = $direction;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    /**
     * @return 'ASC'|'DESC'
     */
    public function getDirection(): string
    {
        return $this->direction;
    }
}
