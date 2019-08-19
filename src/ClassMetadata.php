<?php

declare(strict_types=1);

namespace Brick\ORM;

abstract class ClassMetadata
{
    /**
     * The entity or embeddable class name.
     */
    public string $className;

    /**
     * The list of persistent properties.
     *
     * @var string[]
     */
    public array $properties;

    /**
     * A map of persistent property names to PropertyMapping instances.
     *
     * The keys of this array must be equal to $properties.
     *
     * @var PropertyMapping[]
     */
    public array $propertyMappings;
}
