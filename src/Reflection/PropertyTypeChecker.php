<?php

declare(strict_types=1);

namespace Brick\ORM\Reflection;

/**
 * Infers property types from reflection.
 */
class PropertyTypeChecker
{
    /**
     * Returns the documented type of a property.
     *
     * On PHP >= 7.4, ReflectionProperty::getType() is used.
     * On previous versions, the @ var annotation is parsed. Only `type` or `type|null` is accepted.
     *
     * @param \ReflectionProperty $property
     *
     * @return PropertyType|null
     *
     * @throws \RuntimeException If a non-acceptable type is found.
     */
    public function getPropertyType(\ReflectionProperty $property) : ?PropertyType
    {
        /** @var \ReflectionType|null $type */
        $type = $property->getType();

        if ($type) {
            $propertyType = new PropertyType;
            $propertyType->type = $type->getName();
            $propertyType->isNullable = $type->allowsNull();
            $propertyType->isBuiltin = $type->isBuiltin();

            return $propertyType;
        }

        return null;
    }
}
