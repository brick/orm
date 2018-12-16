<?php

declare(strict_types=1);

namespace Brick\ORM;

use Brick\ORM\Reflection\PropertyTypeChecker;

class EntityConfiguration
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var \ReflectionClass
     */
    private $reflectionClass;

    /**
     * @var string|null
     */
    private $belongsTo;

    /**
     * @var PropertyTypeChecker
     */
    private $propertyTypeChecker;

    /**
     * @var string|null
     */
    private $tableName;

    /**
     * @var bool
     */
    private $isAutoIncrement = false;

    /**
     * The list of identity properties, or null if not set.
     *
     * @var string[]|null
     */
    private $identityProperties;

    /**
     * The discriminator column name, or null if not set.
     *
     * @var string|null
     */
    private $discriminatorColumn;

    /**
     * A map of discriminator values to entity class names, or an empty array if not set.
     *
     * @var string[]
     */
    private $discriminatorMap = [];

    /**
     * @param Configuration $configuration
     * @param string        $className
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(Configuration $configuration, string $className)
    {
        $this->configuration = $configuration;

        try {
            $this->reflectionClass = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            throw new \InvalidArgumentException(sprintf('%s does not exist.', $className), 0, $e);
        }

        $this->propertyTypeChecker = new PropertyTypeChecker();
    }

    /**
     * @return string
     */
    public function getClassName() : string
    {
        return $this->reflectionClass->getName();
    }

    /**
     * @return string
     */
    public function getClassShortName() : string
    {
        return $this->reflectionClass->getShortName();
    }

    /**
     * Sets the root entity of the aggregate this entity belongs to.
     *
     * @param string $className
     *
     * @return EntityConfiguration
     */
    public function belongsTo(string $className) : EntityConfiguration
    {
        $this->belongsTo = $className;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getBelongsTo() : ?string
    {
        return $this->belongsTo;
    }

    /**
     * Sets the table name.
     *
     * If not set, it will default to the entity short name (i.e. the name without the namespace).
     *
     * @param string $tableName
     *
     * @return EntityConfiguration
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
     *
     * @return string
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
     *
     * @return EntityConfiguration
     */
    public function setAutoIncrement() : EntityConfiguration
    {
        $this->isAutoIncrement = true;

        return $this;
    }

    /**
     * Returns whether the database table uses an auto-increment identity field.
     *
     * @return bool
     *
     * @throws \LogicException
     */
    public function isAutoIncrement() : bool
    {
        if ($this->isAutoIncrement) {
            $identityProperties = $this->getIdentityProperties();

            if (count($identityProperties) !== 1) {
                throw new \LogicException(sprintf('The entity %s has multiple identity properties and cannot be mapped to an auto-increment table.', $this->getClassName()));
            }

            // @todo this should also check that the property maps to a single column;
            // maybe, for PHP 7.4, just check that the property type is int or string?
        }

        return $this->isAutoIncrement;
    }

    /**
     * @param string ...$identityProperties
     *
     * @return EntityConfiguration
     *
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
     * @return string[]
     *
     * @throws \LogicException
     */
    public function getIdentityProperties() : array
    {
        if ($this->identityProperties === null) {
            throw new \LogicException(sprintf('No identity properties have been set for class %s.', $this->className));
        }

        foreach ($this->identityProperties as $identityProperty) {
            if (! in_array($identityProperty, $this->getPersistentProperties())) {
                throw new \LogicException(sprintf('Identity property $%s in class %s is not persistent.', $identityProperty, $this->getClassName()));
            }
        }

        return $this->identityProperties;
    }

    /**
     * @param string          $className     The entity class name.
     * @param string          $propertyName  The property name.
     * @param ClassMetadata[] $classMetadata A map of FQCN to ClassMetadata instances for all entities.
     *
     * @return PropertyMapping
     *
     * @throws \LogicException
     */
    public function getPropertyMapping(string $className, string $propertyName, array $classMetadata) : PropertyMapping
    {
        if (! in_array($propertyName, $this->getPersistentProperties($className))) {
            throw new \InvalidArgumentException(sprintf('Cannot return property mapping for unknown or non-persistent property %s::$%s.', $className, $property));
        }

        $propertyType = $this->propertyTypeChecker->getPropertyType(new \ReflectionProperty($className, $propertyName));

        $fieldName = $propertyName; // @todo make configurable

        if ($propertyType->isBuiltin) {
            switch ($propertyType->type) {
                case 'int':
                    return new PropertyMapping\IntMapping($fieldName);

                case 'string':
                    return new PropertyMapping\StringMapping($fieldName);

                case 'bool':
                    return new PropertyMapping\BoolMapping($fieldName);

                default:
                    throw new \LogicException(sprintf('Cannot persist type "%s" in %s::$%s.', $propertyType->type, $className, $propertyName));
            }
        }

        if (! isset($this->configuration->getEntities()[$propertyType->type])) {
            throw new \LogicException(sprintf('Type %s of %s::$%s is not an entity.', $propertyType->type, $className, $propertyName));
        }

        return new PropertyMapping\EntityMapping($classMetadata[$propertyType->type], $fieldName . '_');
    }

    /**
     * Sets the inheritance mapping for this entity.
     *
     * Every persistable class in the hierarchy must have an entry in the discriminator map. This excludes abstract
     * classes, and root classes that are common to several entities (so-called MappedSuperclass in other ORM).
     *
     * Note: only single table inheritance is supported for now.
     *
     * @param string $discriminatorColumn The discriminator column name.
     * @param array  $discriminatorMap    A map of discriminator value to concrete entity class name.
     *
     * @return EntityConfiguration
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
                throw new \InvalidArgumentException(sprintf('%s is not a subclass of %s and cannot be part of its discriminator map.'));
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
     * Returns the discriminator column name, or null if not inheritance is mapped.
     *
     * @return string|null
     */
    public function getDiscriminatorColumn() : ?string
    {
        return $this->discriminatorColumn;
    }

    /**
     * Returns a map of discriminator values to fully-qualified entity class names.
     *
     * If no inheritance is mapped, an empty array is returned.
     *
     * @return string[]
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
     * @param string|null $className The entity class name, or null to use the root entity (this entity)'s class name.
     *
     * @return string[]
     *
     * @throws \LogicException
     */
    public function getPersistentProperties(?string $className = null) : array
    {
        if ($className === null) {
            $reflectionClass = $this->reflectionClass;
        } else {
            $reflectionClass = new \ReflectionClass($className);
        }

        $persistableProperties = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isStatic()) {
                continue;
            }

            $propertyName = $reflectionProperty->getName();

            if (in_array($propertyName, $this->configuration->getTransientProperties($reflectionClass->getName()))) {
                continue;
            }

            if ($reflectionProperty->isPrivate()) {
                throw new \LogicException(sprintf('%s::$%s is private; private properties are not supported. Make the property protected, or add it to transient properties if it should not be persistent.', $className, $propertyName));
            }

            $propertyType = $this->propertyTypeChecker->getPropertyType($reflectionProperty);

            if ($propertyType === null) {
                throw new \LogicException(sprintf('%s::$%s is not typed. Add a type to the property, or add it to transient properties if it should not be persistent.', $className, $propertyName));
            }

            $persistableProperties[] = $propertyName;
        }

        if (count($persistableProperties) === 0) {
            throw new \LogicException(sprintf('%s has not persistable properties.', $className));
        }

        return $persistableProperties;
    }

    /**
     * @param string[] $properties The list of property names to check.
     *
     * @return void
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
