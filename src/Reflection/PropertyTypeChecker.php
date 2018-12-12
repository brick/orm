<?php

declare(strict_types=1);

namespace Brick\ORM\Reflection;

use Brick\Reflection\ImportResolver;

/**
 * Infers property types from reflection.
 *
 * This method uses the new ReflectionProperty::getType() method if available (PHP >= 7.4), and falls back to types
 * documented using @ var annotations if this method is not available or returns null.
 */
class PropertyTypeChecker
{
    private const BUILTIN_TYPES = [
        'bool',
        'int',
        'float',
        'string',
        'array',
        'object',
        'callable',
        'iterable'
    ];

    /**
     * An associative array of ImportResolver objects, indexed by FQCN.
     *
     * @var ImportResolver[]
     */
    private $importResolvers = [];

    /**
     * Whether typed properties are supported.
     *
     * @var bool
     */
    private $hasTypedProperties;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->hasTypedProperties = method_exists(\ReflectionProperty::class, 'getType');
    }

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
        if ($this->hasTypedProperties) {
            /** @var \ReflectionType|null $type */
            $type = $property->getType();

            if ($type) {
                $propertyType = new PropertyType;
                $propertyType->type = $type->getName();
                $propertyType->isNullable = $type->allowsNull();

                return $propertyType;
            }
        }

        $docComment = $property->getDocComment();

        if ($docComment === false) {
            return null;
        }

        $matchCount = preg_match_all('/@var(?:[ \t]+(\S*))?/', $docComment, $matches);

        if ($matchCount === 0) {
            return null;
        }

        if ($matchCount !== 1) {
            throw new \RuntimeException('A property cannot have multiple @var annotations.');
        }

        $types = explode('|', $matches[1][0]);
        $count = count($types);

        if ($count === 0) {
            return null;
        }

        if ($count > 2) {
            throw new \RuntimeException('A property cannot have several types.');
        }

        $isNullable = false;

        if ($count === 2) {
            if (strtolower($types[1]) !== 'null') {
                throw new \RuntimeException('A property can only have a second type that is null.');
            }

            $isNullable = true;
        }

        // $count === 1

        $type = $types[0];

        if ($type === '') {
            throw new \RuntimeException('The type of a property cannot be empty.');
        }

        $typeLower = strtolower($type);

        if ($typeLower === 'null') {
            throw new \RuntimeException('The type of a property cannot be null.');
        }

        if ($typeLower === 'void') {
            throw new \RuntimeException('The type of a property cannot be void.');
        }

        $isBuiltin = in_array($typeLower, self::BUILTIN_TYPES, true);

        if (! $isBuiltin) {
            // Not a built-in type: we need to resolve the type according to the namespace and imports.
            $class = $property->getDeclaringClass();
            $className = $class->getName();

            if (isset($this->importResolvers[$className])) {
                $importResolver = $this->importResolvers[$className];
            } else {
                $importResolver = $this->importResolvers[$className] = new ImportResolver($class);
            }

            $type = $importResolver->resolve($type);
        }

        $propertyType = new PropertyType;

        $propertyType->type = $type;
        $propertyType->isNullable = $isNullable;
        $propertyType->isBuiltin = $isBuiltin;

        return $propertyType;
    }
}
