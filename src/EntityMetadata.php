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
     */
    public ?string $discriminatorColumn;

    /**
     * The discriminator value.
     *
     * This property will only be set if the entity is a concrete class in an inheritance hierarchy.
     * For other entities and abstract entities, this property will be null.
     */
    public ?string $discriminatorValue;

    /**
     * The discriminator map for inheritance.
     *
     * The keys are discriminator strings or ints, the values are class names.
     * If the entity is not part of an inheritance hierarchy, this will be an empty array.
     *
     * This property is set, and is the same, for all classes in the inheritance hiearchy.
     */
    public array $discriminatorMap;

    /**
     * The inverse discriminator map.
     *
     * The keys are class names, the values are discriminator values (as string or int).
     * If the entity is not part of an inheritance hierarchy, this will be an empty array.
     *
     * This property is set, and is the same, for all classes in the inheritance hiearchy.
     */
    public array $inverseDiscriminatorMap;

    /**
     * The root entity class name.
     *
     * If the entity is not part of an inheritance hierarchy, or is itself the root of the hierarchy, this will be the
     * same as the entity class name.
     */
    public string $rootClassName;

    /**
     * The entity's proxy class name.
     *
     * This property is only set if the class is a concrete entity.
     * For abstract entities, this property will be null.
     */
    public ?string $proxyClassName;

    /**
     * The database table name.
     */
    public string $tableName;

    /**
     * The list of persistent property names that are part of the identity.
     *
     * This list must not intersect with $nonIdProperties.
     * The union of $idProperties and $nonIdProperties must be equal to $properties.
     *
     * @var string[]
     */
    public array $idProperties;

    /**
     * The list of persistent property names that are NOT part of the identity.
     *
     * This list must not intersect with $idProperties.
     * The union of $idProperties and $nonIdProperties must be equal to $properties.
     *
     * @var string[]
     */
    public array $nonIdProperties;

    /**
     * The list of persistent property names that are not part of the identity, and are declared in this class only.
     *
     * Properties declared in parent classes are not included here.
     *
     * @var string[]
     */
    public array $selfNonIdProperties;

    /**
     * The list of child entity class names, if any.
     *
     * @var string[]
     */
    public array $childClasses;

    /**
     * Whether the table uses an auto-increment primary key.
     *
     * This is only supported on tables with a single primary key column. If this is true, there must be only one
     * property part of the identity, and this property must map to a single database field.
     */
    public bool $isAutoIncrement;
}
