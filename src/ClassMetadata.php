<?php

declare(strict_types=1);

namespace Brick\ORM;

abstract class ClassMetadata
{
    /**
     * The entity or embeddable class name.
     *
     * @psalm-var class-string
     */
    public string $className;

    /**
     * The list of persistent properties.
     *
     * @psalm-var list<string>
     *
     * @var string[]
     */
    public array $properties;

    /**
     * A map of persistent property names to PropertyMapping instances.
     *
     * The keys of this array must be equal to $properties.
     *
     * @var array<string, PropertyMapping>
     */
    public array $propertyMappings;
}
