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

                default:
                    throw new \LogicException(sprintf('Cannot persist type "%s" in %s::$%s.', $propertyType->type, $className, $propertyName));
            }
        }

        if (! isset($classMetadata[$propertyType->type])) {
            throw new \LogicException(sprintf('Type %s of %s::$%s is not an entity or embeddable.', $propertyType->type, $className, $propertyName));
        }

        $classMetadata = $classMetadata[$propertyType->type];

        $fieldNamePrefixes = $this->configuration->getFieldNamePrefixes();
        $fieldNamePrefix = $fieldNamePrefixes[$className][$propertyName] ?? $propertyName . '_';

        if ($classMetadata instanceof EntityMetadata) {
            return new PropertyMapping\EntityMapping($classMetadata, $fieldNamePrefix, $propertyType->isNullable);
        }

        if ($classMetadata instanceof EmbeddableMetadata) {
            return new PropertyMapping\EmbeddableMapping($classMetadata, $fieldNamePrefix, $propertyType->isNullable);
        }

        throw new \LogicException('Unknown metadata class.');
    }
}
