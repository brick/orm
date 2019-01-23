<?php

declare(strict_types=1);

namespace Brick\ORM\Exception;

/**
 * Exception thrown when a query returns several results, and at most one was expected.
 */
class NonUniqueResultException extends ORMException
{
    /**
     * @param int $resultCount
     *
     * @return NonUniqueResultException
     */
    public static function nonUniqueResult(int $resultCount) : self
    {
        return new self(sprintf('The query returned %d results, when at most 1 was expected.', $resultCount));
    }
}
