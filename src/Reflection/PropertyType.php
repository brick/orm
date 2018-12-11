<?php

declare(strict_types=1);

namespace Brick\ORM\Reflection;

/**
 * The type of a property.
 */
class PropertyType
{
    /**
     * @var string
     */
    public $type;

    /**
     * @var bool
     */
    public $isNullable;
}
