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
     * The name of the discriminator column, or null if the entity is not part of an inheritance hierarchy.
     *
     * @var string|null
     */
    public $discriminatorColumn;

    /**
     * The discriminator value, or null if the entity is not part of an inheritance hierarchy, or if the entity is an
     * abstract class in the inheritance hierarchy.
     *
     * @var string|null
     */
    public $discriminatorValue;

    /**
     * The discriminator map for inheritance.
     *
     * The keys are discriminator strings or ints, the values are class names.
     * If the entity is not part of an inheritance hierarchy, this will be an empty array.
     *
     * @var array
     */
    public $discriminatorMap;

    /**
     * The entity's proxy class name, or null if the entity class is abstract.
     *
     * @var string|null
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
