<?php

declare(strict_types=1);

namespace Brick\ORM\Exception;

/**
 * Exception thrown when attempting when two instances with the same identity conflict.
 */
class IdentityConflictException extends ORMException
{
    /**
     * @psalm-param class-string $className
     * @psalm-param list<int|string> $scalarIdentity
     *
     * @param string $className      The entity class name.
     * @param array  $scalarIdentity The identity, as a list of scalar values.
     *
     * @return IdentityConflictException
     */
    public static function identityMapConflict(string $className, array $scalarIdentity) : self
    {
        return new self(sprintf(
            'The instance of entity type %s with identity %s cannot be added to the identity map, ' .
            'because another instance of this type with the same identity already exists.',
            $className,
            self::exportScalarIdentity($scalarIdentity)
        ));
    }
}
