<?php

declare(strict_types=1);

namespace Brick\ORM;

/**
 * The identity map.
 *
 * For performance reasons, no consistency checks are performed. In particular, it is assumed that:
 *
 * - identity arrays contain integers, strings and objects (other entities) only;
 * - multiple calls to set() and get() for a given class name always contain the same number and type of elements for
 *   the identity, and always in the same order.
 *
 * These conditions must always be met by the ORM classes that consume the identity map.
 * Failure to meet these conditions would result in PHP errors.
 */
class IdentityMap
{
    /**
     * The entities, indexed by root class name and identity (one nested array per identity value).
     *
     * @var array
     */
    private $entities = [];

    /**
     * Retrieves an entity from the identity map.
     *
     * @param string $class    The root entity class name.
     * @param array  $identity The list of values that form the entity's identity.
     *
     * @return object|null The entity, or null if not found.
     */
    public function get(string $class, array $identity) : ?object
    {
        $ref = & $this->entities[$class];

        foreach ($identity as $key) {
            if (is_object($key)) {
                $key = spl_object_id($key);
            }

            $ref = & $ref[$key];
        }

        return $ref;
    }

    /**
     * Adds an entity to the identity map.
     *
     * If an entity already exists under this identity, it is replaced.
     *
     * @param string $class    The root entity class name.
     * @param array  $identity The list of values that form the entity's identity.
     * @param object $entity   The entity to add.
     *
     * @return void
     */
    public function set(string $class, array $identity, object $entity) : void
    {
        $ref = & $this->entities[$class];

        foreach ($identity as $key) {
            if (is_object($key)) {
                $key = spl_object_id($key);
            }

            $ref = & $ref[$key];
        }

        $ref = $entity;
    }
}
