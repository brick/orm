<?php

declare(strict_types=1);

namespace Brick\ORM\Exception;

/**
 * Exception found when attempting to read the identity of an entity that does not have one.
 */
class NoIdentityException extends ORMException
{
}
