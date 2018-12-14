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
     * The list of persistent properties, or null if not set.
     *
     * @var string[]|null
     */
    private $persistentProperties;

    /**
     * The list of identity properties, or null if not set.
     *
     * @var string[]|null
     */
    private $identityProperties;

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
            throw new \InvalidArgumentException('The entity class name is invalid.', 0, $e);
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
     * Sets the properties that will be persisted to the database.
     *
     * Note: use setPersistentProperties() OR setTransientProperties(), not both.
     *
     * @param string ...$properties
     *
     * @return EntityConfiguration
     *
     * @throws \InvalidArgumentException
     */
    public function setPersistentProperties(string ...$properties) : EntityConfiguration
    {
        if (count($properties) === 0) {
            throw new \InvalidArgumentException('The list of persistent properties cannot be empty.');
        }

        $this->checkProperties($properties);

        $this->persistentProperties = $properties;

        return $this;
    }

    /**
     * Sets the properties that will *not* be persisted to the database.
     *
     * The persistent properties will be all of the persistable class properties, minus these ones.
     *
     * Note: use setPersistentProperties() OR setTransientProperties(), not both.
     *
     * @param string ...$transientProperties
     *
     * @return EntityConfiguration
     *
     * @throws \LogicException
     */
    public function setTransientProperties(string ...$transientProperties) : EntityConfiguration
    {
        $this->checkProperties($transientProperties);

        $persistentProperties = [];

        foreach ($this->getPersistableProperties() as $persistableProperty) {
            if (! in_array($persistableProperty, $transientProperties)) {
                $persistentProperties[] = $persistableProperty;
            }
        }

        if (count($persistentProperties) === 0) {
            throw new \LogicException('Cannot make all entity properties transient.');
        }

        $this->persistentProperties = $persistentProperties;

        return $this;
    }

    /**
     * Returns the list of properties that will be persisted to the database.
     *
     * @return string[]
     *
     * @throws \LogicException
     */
    public function getPersistentProperties() : array
    {
        if ($this->persistentProperties === null) {
            $this->persistentProperties = $this->getPersistableProperties();
        }

        return $this->persistentProperties;
    }

    /**
     * @param string          $propertyName  The property name.
     * @param ClassMetadata[] $classMetadata A map of FQCN to ClassMetadata instances for all entities.
     *
     * @return PropertyMapping
     *
     * @throws \LogicException
     */
    public function getPropertyMapping(string $propertyName, array $classMetadata) : PropertyMapping
    {
        if (! in_array($propertyName, $this->getPersistentProperties())) {
            throw new \InvalidArgumentException(sprintf('Cannot return property mapping for unknown or non-persistent property %s::$%s.', $this->getClassName(), $property));
        }

        $propertyType = $this->propertyTypeChecker->getPropertyType(new \ReflectionProperty($this->getClassName(), $propertyName));

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
                    throw new \LogicException(sprintf('Cannot persist type "%s" in %s::$%s.', $propertyType->type, $this->getClassName(), $propertyName));
            }
        }

        if (! isset($this->configuration->getEntities()[$propertyType->type])) {
            throw new \LogicException(sprintf('Type %s of %s::$%s is not an entity.', $propertyType->type, $this->getClassName(), $propertyName));
        }

        return new PropertyMapping\EntityMapping($classMetadata[$propertyType->type], $fieldName . '_');
    }

    /**
     * @return string[]
     *
     * @throws \LogicException
     */
    private function getPersistableProperties() : array
    {
        $persistableProperties = [];

        foreach ($this->reflectionClass->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isStatic()) {
                continue;
            }

            $property = $reflectionProperty->getName();

            if ($reflectionProperty->isPrivate()) {
                throw new \LogicException(sprintf('%s::$%s is private; private properties are not supported. Make the property protected, or add it to transient properties if it should not be persistent.', $this->getClassName(), $property));
            }

            $propertyType = $this->propertyTypeChecker->getPropertyType($reflectionProperty);

            if ($propertyType === null) {
                throw new \LogicException(sprintf('%s::$%s is not typed. Add a type to the property, or add it to transient properties if it should not be persistent.', $this->getClassName(), $property));
            }

            $persistableProperties[] = $property;
        }

        if (count($persistableProperties) === 0) {
            throw new \LogicException(sprintf('Class %s has not persistable properties.', $this->getClassName()));
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
                throw new \InvalidArgumentException(sprintf('Class %s has no property named $%s.', $this->getClassName(), $property));
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
