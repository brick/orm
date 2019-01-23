<?php

declare(strict_types=1);

namespace Brick\ORM\Exception;

/**
 * Exception thrown when attempting to perform operations on an unknown entity class.
 */
class UnknownEntityClassException extends ORMException
{
    /**
     * @param string $class
     *
     * @return UnknownEntityClassException
     */
    public static function unknownEntityClass(string $class) : self
    {
        return new self(sprintf('Unknown entity class "%s".', $class));
    }
}
