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
     * The entity's proxy class name.
     *
     * @var string
     */
    public $proxyClassName;

    /**
     * The database table name.
     *
     * @var string
     */
    public $tableName;

    /**
     * The list of persistent properties.
     *
     * This list must be the union of $idProperties and $nonIdProperties.
     *
     * @var string[]
     */
    public $properties;

    /**
     * The list of property names that are part of the identity.
     *
     * This list must not intersect with $nonIdProperties.
     *
     * @var string[]
     */
    public $idProperties;

    /**
     * The list of property names that are NOT part of the identity.
     *
     * This list must not intersect with $idProperties.
     *
     * @var string[]
     */
    public $nonIdProperties;

    /**
     * A map of persistent property names to PropertyMapping instances.
     *
     * The keys of this array must be equal to $properties.
     *
     * @var PropertyMapping[]
     */
    public $propertyMappings;

    /**
     * Whether the table uses an auto-increment primary key.
     *
     * This is only supported on tables with a single primary key column. If this is true, there must be only one
     * property part of the identity, and this property must map to a single database field.
     *
     * @var bool
     */
    public $isAutoIncrement;
}
