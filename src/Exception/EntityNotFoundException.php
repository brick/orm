<?php

declare(strict_types=1);

namespace Brick\ORM\Exception;

use function sprintf;

/**
 * Exception thrown when attempting to load an entity that does not exist in the database.
 *
 * This exception will typically be thrown when attempting to load a proxy, or manually hydrate an entity, whose
 * identity is not found in the database.
 */
class EntityNotFoundException extends ORMException
{
    /**
     * @param class-string     $className      The entity class name.
     * @param list<int|string> $scalarIdentity The identity, as a list of int or string values.
     */
    public static function entityNotFound(string $className, array $scalarIdentity): self
    {
        return new self(sprintf(
            'The entity %s with identity %s was not found.',
            $className,
            self::exportScalarIdentity($scalarIdentity),
        ));
    }
}
