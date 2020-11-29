<?php

declare(strict_types=1);

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\EmbeddableMetadata;
use Brick\ORM\ObjectFactory;
use Brick\ORM\Gateway;
use Brick\ORM\PropertyMapping;
use ReflectionObject;

/**
 * @internal
 */
class EmbeddableMapping implements PropertyMapping
{
    /**
     * The class metadata of the target entity.
     */
    public EmbeddableMetadata $classMetadata;

    public string $fieldNamePrefix;

    public bool $isNullable;

    /**
     * @param EmbeddableMetadata $classMetadata   The target entity class metadata.
     * @param string             $fieldNamePrefix The prefix for field names.
     * @param bool               $isNullable      Whether the property is nullable.
     */
    public function __construct(EmbeddableMetadata $classMetadata, string $fieldNamePrefix, bool $isNullable)
    {
        $this->classMetadata   = $classMetadata;
        $this->fieldNamePrefix = $fieldNamePrefix;
        $this->isNullable      = $isNullable;
    }

    public function getType() : string|null
    {
        return $this->classMetadata->className;
    }

    public function isNullable() : bool
    {
        return $this->isNullable;
    }

    /**
     * @todo precompute for better performance
     */
    public function getFieldNames() : array
    {
        $names = [];

        foreach ($this->classMetadata->properties as $prop) {
            foreach ($this->classMetadata->propertyMappings[$prop]->getFieldNames() as $name) {
                $names[] = $this->fieldNamePrefix . $name;
            }
        }

        return $names;
    }

    /**
     * @todo precompute for better performance
     */
    public function getInputValuesCount() : int
    {
        $count = 0;

        foreach ($this->classMetadata->properties as $prop) {
            $propertyMapping = $this->classMetadata->propertyMappings[$prop];
            $count += $propertyMapping->getInputValuesCount();
        }

        return $count;
    }

    /**
     * @todo precompute for better performance
     */
    public function getFieldToInputValuesSQL(array $fieldNames) : array
    {
        $wrappedFields = [];
        $currentIndex = 0;

        foreach ($this->classMetadata->properties as $prop) {
            $propertyMapping = $this->classMetadata->propertyMappings[$prop];
            $readFieldCount = $propertyMapping->getInputValuesCount();

            $currentFieldNames = array_slice($fieldNames, $currentIndex, $readFieldCount);
            $currentIndex += $readFieldCount;

            foreach ($propertyMapping->getFieldToInputValuesSQL($currentFieldNames) as $wrappedField) {
                $wrappedFields[] = $wrappedField;
            }
        }

        return $wrappedFields;
    }

    public function convertInputValuesToProp(Gateway $gateway, array $values) : mixed
    {
        $currentIndex = 0;

        $propValues = [];

        foreach ($this->classMetadata->properties as $prop) {
            $propertyMapping = $this->classMetadata->propertyMappings[$prop];
            $readFieldCount = $propertyMapping->getInputValuesCount();

            $currentInputValues = array_slice($values, $currentIndex, $readFieldCount);
            $currentIndex += $readFieldCount;

            $propValues[$prop] = $propertyMapping->convertInputValuesToProp($gateway, $currentInputValues);
        }

        // @todo keep an ObjectFactory cache.
        $objectFactory = new ObjectFactory();

        return $objectFactory->instantiate($this->classMetadata, $propValues);
    }

    public function convertPropToFields(mixed $propValue) : array
    {
        $result = [];

        /** @var object|null $entity */
        $entity = $propValue;

        if ($entity !== null) {
            $r = new \ReflectionObject($entity);
        }

        foreach ($this->classMetadata->properties as $prop) {
            if ($entity === null) {
                $idPropValue = null;
            } else {
                /** @psalm-var ReflectionObject $r */
                $p = $r->getProperty($prop);
                $p->setAccessible(true);
                $idPropValue = $p->getValue($entity);
            }

            foreach ($this->classMetadata->propertyMappings[$prop]->convertPropToFields($idPropValue) as $expressionAndValues) {
                $result[] = $expressionAndValues;
            }
        }

        return $result;
    }
}
