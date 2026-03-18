<?php

declare(strict_types=1);

namespace Brick\ORM\Exception;

use function sprintf;

/**
 * Exception thrown when refering to an unknown persistent class property.
 */
class UnknownPropertyException extends ORMException
{
    /**
     * @param class-string $class
     */
    public static function unknownProperty(string $class, string $property): self
    {
        return new self(sprintf('Class "%s" has no persistent property named "%s".', $class, $property));
    }

    /**
     * @param class-string $class
     */
    public static function invalidDottedProperty(string $class, string $dottedProperty): self
    {
        return new self(sprintf('"%s" is not a valid property for "%s".', $dottedProperty, $class));
    }
}
