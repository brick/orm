<?php

declare(strict_types=1);

namespace Brick\ORM;

/**
 * The lock modes used when retrieving entities from the database.
 */
class LockMode
{
    /**
     * Loads the entities with no lock.
     *
     * If the entities already exist in the identity map, they are returned as is and are not refreshed.
     */
    public const NONE = 0;

    /**
     * Loads the entities with a read lock.
     *
     * If the entities already exist in the identity map, they are refreshed, overwriting any in-memory changes.
     */
    public const READ = 1;

    /**
     * Loads the entities with a write lock.
     *
     * If the entities already exist in the identity map, they are refreshed, overwriting any in-memory changes.
     */
    public const WRITE = 2;
}
