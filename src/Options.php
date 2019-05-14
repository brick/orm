<?php

declare(strict_types=1);

namespace Brick\ORM;

/**
 * The options that can be used when retrieving entities from the database.
 */
class Options
{
    /**
     * Loads the entities with a read lock. Implies REFRESH.
     */
    public const LOCK_READ = 1 << 0;

    /**
     * Loads the entities with a write lock. Implies REFRESH.
     */
    public const LOCK_WRITE = 1 << 1;

    /**
     * Skips already locked entities. This must be OR'ed with READ or WRITE.
     */
    public const SKIP_LOCKED = 1 << 2;

    /**
     * Refreshes the entity if it is already present in the identity map, overwriting any in-memory changes.
     */
    public const REFRESH = 1 << 3;
}
