<?php

declare(strict_types=1);

namespace Brick\ORM;

class EntityMetadata extends ClassMetadata
{
    /**
     * The name of the discriminator column.
     *
     * This property will only be set if the entity is part of an inheritance hierarchy.
     * For other entities, this property will be null.
     *
     * @var string|null
     */
    public $discriminatorColumn;

    /**
     * The discriminator value.
     *
     * This property will only be set if the entity is a concrete class in an inheritance hierarchy.
     * For other entities and abstract entities, this property will be null.
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
     * The entity's proxy class name.
     *
     * This property is only set if the class is a concrete entity.
     * For abstract entities, this property will be null.
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
     * The list of persistent property names that are part of the identity.
     *
     * This list must not intersect with $nonIdProperties.
     * The union of $idProperties and $nonIdProperties must be equal to $properties.
     *
     * @var string[]
     */
    public $idProperties;

    /**
     * The list of persistent property names that are NOT part of the identity.
     *
     * This list must not intersect with $idProperties.
     * The union of $idProperties and $nonIdProperties must be equal to $properties.
     *
     * @var string[]
     */
    public $nonIdProperties;

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
