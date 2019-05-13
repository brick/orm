<?php

declare(strict_types=1);

namespace Brick\ORM;

use Brick\ORM\Reflection\PropertyTypeChecker;

abstract class ClassConfiguration
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var \ReflectionClass
     */
    protected $reflectionClass;

    /**
     * @var PropertyTypeChecker
     */
    protected $propertyTypeChecker;

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

        $className = $reflectionClass->getName();

        $persistableProperties = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isStatic()) {
                continue;
            }

            $propertyName = $reflectionProperty->getName();

            if (in_array($propertyName, $this->configuration->getTransientProperties($className))) {
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
     * @param string               $className          The entity class name.
     * @param string               $propertyName       The property name.
     * @param EntityMetadata[]     $entityMetadata     A map of FQCN to EntityMetadata instances.
     * @maram EmbeddableMetadata[] $embeddableMetadata A map of FQCN to EmbeddableMetadata instances.
     *
     * @return PropertyMapping
     *
     * @throws \LogicException
     */
    public function getPropertyMapping(string $className, string $propertyName, array $entityMetadata, array $embeddableMetadata) : PropertyMapping
    {
        if (! in_array($propertyName, $this->getPersistentProperties($className))) {
            throw new \InvalidArgumentException(sprintf('Cannot return property mapping for unknown or non-persistent property %s::$%s.', $className, $propertyName));
        }

        $customPropertyMappings = $this->configuration->getCustomPropertyMappings();

        if (isset($customPropertyMappings[$className][$propertyName])) {
            return $customPropertyMappings[$className][$propertyName];
        }

        $propertyType = $this->propertyTypeChecker->getPropertyType(new \ReflectionProperty($className, $propertyName));

        $fieldNames = $this->configuration->getFieldNames();
        $fieldName = $fieldNames[$className][$propertyName] ?? $propertyName;

        if ($propertyType->isBuiltin) {
            switch ($propertyType->type) {
                case 'int':
                    return new PropertyMapping\IntMapping($fieldName, $propertyType->isNullable);

                case 'string':
                    return new PropertyMapping\StringMapping($fieldName, $propertyType->isNullable);

                case 'bool':
                    return new PropertyMapping\BoolMapping($fieldName, $propertyType->isNullable);

                case 'array':
                    throw new \LogicException(sprintf('Cannot persist type "array" in %s::$%s; you can store an array as JSON if you wish, by configuring a custom JsonMapping instance.', $className, $propertyName));

                default:
                    throw new \LogicException(sprintf('Cannot persist type "%s" in %s::$%s.', $propertyType->type, $className, $propertyName));
            }
        }

        $customMappings = $this->configuration->getCustomMappings();

        if (isset($customMappings[$propertyType->type])) {
            // @todo for now this only works with a single field name/prefix, and fixed constructor
            return new $customMappings[$propertyType->type]($fieldName, $propertyType->isNullable);
        }

        $fieldNamePrefixes = $this->configuration->getFieldNamePrefixes();
        $fieldNamePrefix = $fieldNamePrefixes[$className][$propertyName] ?? $propertyName . '_';

        if (isset($entityMetadata[$propertyType->type])) {
            return new PropertyMapping\EntityMapping($entityMetadata[$propertyType->type], $fieldNamePrefix, $propertyType->isNullable);
        }

        if (isset($embeddableMetadata[$propertyType->type])) {
            return new PropertyMapping\EmbeddableMapping($embeddableMetadata[$propertyType->type], $fieldNamePrefix, $propertyType->isNullable);
        }

        throw new \LogicException(sprintf('Type %s of %s::$%s is not an entity or embeddable, and has no custom mapping defined.', $propertyType->type, $className, $propertyName));
    }
}
