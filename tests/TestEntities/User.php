<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\TestEntities;

/**
 * @todo PHP 7.4: add all combinations of properties to test ObjectFactory:
 *  - int $x
 *  - int $x = 1,
 *  - ?int $x
 *  - ?int $x = null
 *  - ?int $x = 1
 *  - $x
 *  - $x = null
 *  - $x = 1
 */
class User
{
    protected $id;

    protected $name;

    protected $status = 'active';

    protected $reputation = 0;
}
