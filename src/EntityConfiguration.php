<?php

declare(strict_types=1);

namespace Brick\ORM;

use ReflectionNamedType;

class EntityConfiguration extends ClassConfiguration
{
    private string|null $belongsTo = null;

    private string|null $tableName = null;

    private bool $isAutoIncrement = false;

    /**
     * The list of identity properties, or null if not set.
     *
     * @psalm-var list<string>|null
     *
     * @var string[]|null
     */
    private array|null $identityProperties = null;

    /**
     * The discriminator column name, or null if not set.
     */
    private string|null $discriminatorColumn = null;

    /**
     * A map of discriminator values to entity class names, or an empty array if not set.
     *
     * @psalm-var array<int|string, class-string>
     *
     * @var string[]
     */
    private array $discriminatorMap = [];

    /**
     * Sets the root entity of the aggregate this entity belongs to.
     *
     * @psalm-param class-string $className
     */
    public function belongsTo(string $className) : EntityConfiguration
    {
        $this->belongsTo = $className;

        return $this;
    }

    public function getBelongsTo() : string|null
    {
        return $this->belongsTo;
    }

    /**
     * Sets the table name.
     *
     * If not set, it will default to the entity short name (i.e. the name without the namespace).
     */
    public function setTableName(string $tableName) : EntityConfiguration
    {
        $this->tableName = $tableName;

        return $this;
    }

    /**
     * Returns the table name.
     *
     * If not set, it will default to the entity short name (i.e. the name without the namespace).
     */
    public function getTableName() : string
    {
        if ($this->tableName !== null) {
            return $this->tableName;
        }

        return $this->reflectionClass->getShortName();
    }

    /**
     * Sets whether the database table uses an auto-increment identity field.
     */
    public function setAutoIncrement() : EntityConfiguration
    {
        $this->isAutoIncrement = true;

        return $this;
    }

    /**
     * Returns whether the database table uses an auto-increment identity field.
     *
     * @throws \LogicException
     */
    public function isAutoIncrement() : bool
    {
        if ($this->isAutoIncrement) {
            $identityProperties = $this->getIdentityProperties();

            if (count($identityProperties) !== 1) {
                throw new \LogicException(sprintf(
                    'The entity "%s" has multiple identity properties and cannot be mapped to an auto-increment table.',
                    $this->getClassName()
                ));
            }

            $reflectionProperty = $this->reflectionClass->getProperty($identityProperties[0]);

            $propertyType = $reflectionProperty->getType();

            if ($propertyType instanceof ReflectionNamedType) {
                $type = $propertyType->getName();

                if ($type !== 'int' && $type !== 'string') {
                    throw new \LogicException(sprintf(
                        'The entity "%s" has an auto-increment identity that maps to an unsupported type "%s", ' .
                        'only int and string are allowed.',
                        $this->getClassName(),
                        $type
                    ));
                }
            } else {
                throw new \LogicException(sprintf(
                    'The entity "%s" has an auto-increment identity that maps to an untyped or union type property, ' .
                    'only int and string are allowed.',
                    $this->getClassName()
                ));
            }
        }

        return $this->isAutoIncrement;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setIdentityProperties(string ...$identityProperties) : EntityConfiguration
    {
        if (count($identityProperties) === 0) {
            throw new \InvalidArgumentException('The list of identity properties cannot be empty.');
        }

        $this->checkProperties($identityProperties);

        $this->identityProperties = $identityProperties;

        return $this;
    }

    /**
     * Returns the list of properties that are part of the entity's identity.
     *
     * @psalm-return list<string>
     *
     * @return string[]
     *
     * @throws \LogicException
     */
    public function getIdentityProperties() : array
    {
        if ($this->identityProperties === null) {
            throw new \LogicException(sprintf('No identity properties have been set for class %s.', $this->getClassName()));
        }

        foreach ($this->identityProperties as $identityProperty) {
            if (! in_array($identityProperty, $this->getPersistentProperties())) {
                throw new \LogicException(sprintf('Identity property $%s in class %s is not persistent.', $identityProperty, $this->getClassName()));
            }
        }

        return $this->identityProperties;
    }

    /**
     * Sets the inheritance mapping for this entity.
     *
     * Every persistable class in the hierarchy must have an entry in the discriminator map. This excludes abstract
     * classes, and root classes that are common to several entities (so-called MappedSuperclass in other ORM).
     *
     * Note: only single table inheritance is supported for now.
     *
     * @psalm-param array<int|string, class-string> $discriminatorMap
     *
     * @param string $discriminatorColumn The discriminator column name.
     * @param array  $discriminatorMap    A map of discriminator value to concrete entity class name.
     *
     * @throws \InvalidArgumentException If the discriminator map is empty, a class name does not exist, or is not a subclass of the root entity class.
     */
    public function setInheritanceMapping(string $discriminatorColumn, array $discriminatorMap) : EntityConfiguration
    {
        if (! $discriminatorMap) {
            throw new \InvalidArgumentException('The discriminator map cannot be empty.');
        }

        $rootEntityClassName = $this->reflectionClass->getName();

        foreach ($discriminatorMap as $discriminatorValue => $className) {
            try {
                $reflectionClass = new \ReflectionClass($className);
            } catch (\ReflectionException $e) {
                throw new \InvalidArgumentException(sprintf('%s does not exist.', $className), 0, $e);
            }

            if ($reflectionClass->isAbstract()) {
                throw new \InvalidArgumentException(sprintf('Abstract class %s cannot be part of the discriminator map.', $reflectionClass->getName()));
            }

            if ($reflectionClass->getName() !== $rootEntityClassName && ! $reflectionClass->isSubclassOf($rootEntityClassName)) {
                throw new \InvalidArgumentException(sprintf('%s is not a subclass of %s and cannot be part of its discriminator map.', $reflectionClass->getName(), $rootEntityClassName));
            }

            // Override to fix potential wrong case
            $discriminatorMap[$discriminatorValue] = $reflectionClass->getName();
        }

        // Check that values are unique
        if (count(array_unique($discriminatorMap)) !== count($discriminatorMap)) {
            throw new \InvalidArgumentException('Duplicate class names in discriminator map.');
        }

        $this->discriminatorColumn = $discriminatorColumn;
        $this->discriminatorMap    = $discriminatorMap;

        return $this;
    }

    /**
     * Returns the discriminator column name, or null if inheritance is not in use.
     */
    public function getDiscriminatorColumn() : string|null
    {
        return $this->discriminatorColumn;
    }

    /**
     * Returns a map of discriminator values to fully-qualified entity class names.
     *
     * If no inheritance is mapped, an empty array is returned.
     *
     * @psalm-return array<int|string, class-string>
     */
    public function getDiscriminatorMap() : array
    {
        return $this->discriminatorMap;
    }

    /**
     * Returns the list of classes part of the hierarchy, starting with the root class (this entity).
     *
     * If this entity is part of an inheritance hierarchy, the result includes all the classes in the discriminator map,
     * plus any abstract class present between the root class and these classes.
     *
     * If this entity is not part of an inheritance hierarchy, an array with a single ReflectionClass instance, for this
     * entity, is returned.
     *
     * @psalm-return class-string[]
     *
     * @return string[] The list of all class names in the hierarchy.
     */
    public function getClassHierarchy() : array
    {
        $classes = [
            $this->getClassName() // root entity
        ];

        foreach ($this->discriminatorMap as $className) {
            $reflectionClass = new \ReflectionClass($className);

            while ($reflectionClass->getName() !== $this->getClassName()) {
                $classes[] = $reflectionClass->getName();
                $reflectionClass = $reflectionClass->getParentClass();
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * @psalm-param list<string> $properties
     *
     * @param string[] $properties The list of property names to check.
     *
     * @throws \InvalidArgumentException If a property does not exist.
     */
    private function checkProperties(array $properties) : void
    {
        foreach ($properties as $property) {
            try {
                $reflectionProperty = $this->reflectionClass->getProperty($property);
            } catch (\ReflectionException $e) {
                throw new \InvalidArgumentException(sprintf('Class %s has no property named $%s.', $this->getClassName(), $property), 0, $e);
            }

            if ($reflectionProperty->isStatic()) {
                throw new \InvalidArgumentException(sprintf('%s::$%s is static; static properties cannot be persisted.', $this->getClassName(), $property));
            }

            if ($reflectionProperty->isPrivate()) {
                throw new \InvalidArgumentException(sprintf('%s::$%s is private; private properties are not supported.', $this->getClassName(), $property));
            }
        }
    }
}
