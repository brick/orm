<?php

declare(strict_types=1);

namespace Brick\ORM;

use Brick\ORM\Exception\IdentityConflictException;

/**
 * The identity map.
 *
 * For performance reasons, no consistency checks are performed. In particular, it is assumed that:
 *
 * - identity arrays contain integers and strings only;
 * - multiple calls to set() and get() for a given class name always contain the same number of elements for the
 *   identity, and always in the same order.
 *
 * These conditions must always be met by the ORM classes that consume the identity map.
 * Failure to meet these conditions would result in PHP errors.
 */
class IdentityMap
{
    /**
     * The entities, indexed by root class name and identity (one nested array per identity value).
     */
    private array $entities = [];

    /**
     * Retrieves an entity from the identity map.
     *
     * @psalm-suppress MixedArrayAccess
     * @psalm-suppress MixedInferredReturnType
     * @psalm-suppress MixedReturnStatement
     *
     * @psalm-param class-string $class
     * @psalm-param list<int|string> $identity
     *
     * @param string $class    The root entity class name.
     * @param array  $identity The list of scalar values that form the entity's identity.
     *
     * @return object|null The entity, or null if not found.
     */
    public function get(string $class, array $identity) : object|null
    {
        $ref = & $this->entities[$class];

        foreach ($identity as $key) {
            $ref = & $ref[$key];
        }

        return $ref;
    }

    /**
     * Adds an entity to the identity map.
     *
     * If the entity already exists in the identity map, this method does nothing.
     * If another entity already exists under this identity, an exception is thrown.
     *
     * @psalm-suppress MixedArrayAccess
     *
     * @psalm-param class-string $class
     * @psalm-param list<int|string> $identity
     *
     * @param string $class    The root entity class name.
     * @param array  $identity The list of scalar values that form the entity's identity.
     * @param object $entity   The entity to add.
     *
     * @return void
     *
     * @throws IdentityConflictException If another instance with the same identity already exists.
     */
    public function set(string $class, array $identity, object $entity) : void
    {
        $ref = & $this->entities[$class];

        foreach ($identity as $key) {
            $ref = & $ref[$key];
        }

        if ($ref !== null && $ref !== $entity) {
            throw IdentityConflictException::identityMapConflict($class, $identity);
        }

        $ref = $entity;
    }
}
