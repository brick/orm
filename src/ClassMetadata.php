<?php

declare(strict_types=1);

namespace Brick\ORM;

class ClassMetadata
{
    /**
     * The entity class name.
     *
     * @var string
     */
    public $className;

    /**
     * The database table name.
     *
     * @var string
     */
    public $tableName;

    /**
     * An associative array mapping property names to ClassProperty instances.
     *
     * @var ClassProperty[]
     */
    public $properties;

    /**
     * A numeric array of property names that are part of the identity.
     *
     * @var string[]
     */
    public $idProperties;

    /**
     * A numeric array of property names that are NOT part of the identity.
     *
     * @var string[]
     */
    public $nonIdProperties;
}
