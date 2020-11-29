<?php

declare(strict_types=1);

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\EntityMetadata;
use Brick\ORM\PropertyMapping;
use Brick\ORM\Gateway;

/**
 * @internal
 */
class EntityMapping implements PropertyMapping
{
    /**
     * The class metadata of the target entity.
     */
    public EntityMetadata $classMetadata;

    public string $fieldNamePrefix;

    public bool $isNullable;

    /**
     * @param EntityMetadata $classMetadata   The target entity class metadata.
     * @param string         $fieldNamePrefix The prefix for field names.
     * @param bool           $isNullable      Whether the property is nullable.
     */
    public function __construct(EntityMetadata $classMetadata, string $fieldNamePrefix, bool $isNullable)
    {
        $this->classMetadata   = $classMetadata;
        $this->fieldNamePrefix = $fieldNamePrefix;
        $this->isNullable      = $isNullable;
    }

    /**
     * {@inheritdoc}
     */
    public function getType() : string|null
    {
        return $this->classMetadata->className;
    }

    /**
     * {@inheritdoc}
     */
    public function isNullable() : bool
    {
        return $this->isNullable;
    }

    /**
     * @todo precompute for better performance
     *
     * {@inheritdoc}
     */
    public function getFieldNames() : array
    {
        $names = [];

        if ($this->classMetadata->discriminatorColumn !== null) {
            $names[] = $this->fieldNamePrefix . $this->classMetadata->discriminatorColumn;
        }

        foreach ($this->classMetadata->idProperties as $prop) {
            foreach ($this->classMetadata->propertyMappings[$prop]->getFieldNames() as $name) {
                $names[] = $this->fieldNamePrefix . $name;
            }
        }

        return $names;
    }

    /**
     * @todo precompute for better performance
     *
     * {@inheritdoc}
     */
    public function getInputValuesCount() : int
    {
        $count = 0;

        if ($this->classMetadata->discriminatorColumn !== null) {
            $count++;
        }

        foreach ($this->classMetadata->idProperties as $prop) {
            $propertyMapping = $this->classMetadata->propertyMappings[$prop];
            $count += $propertyMapping->getInputValuesCount();
        }

        return $count;
    }

    /**
     * @todo precompute for better performance
     *
     * {@inheritdoc}
     */
    public function getFieldToInputValuesSQL(array $fieldNames) : array
    {
        $wrappedFields = [];
        $currentIndex = 0;

        if ($this->classMetadata->discriminatorColumn !== null) {
            $wrappedFields[] = $fieldNames[$currentIndex++];
        }

        foreach ($this->classMetadata->idProperties as $prop) {
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

    /**
     * {@inheritdoc}
     */
    public function convertInputValuesToProp(Gateway $gateway, array $values) : mixed
    {
        $currentIndex = 0;

        if ($this->classMetadata->discriminatorColumn !== null) {
            /** @var int|string|null $discriminatorValue */
            $discriminatorValue = $values[$currentIndex++];

            if ($discriminatorValue === null) {
                return null;
            }

            $className = $this->classMetadata->discriminatorMap[$discriminatorValue];
        } else {
            $className = $this->classMetadata->className;
        }

        $id = [];

        foreach ($this->classMetadata->idProperties as $prop) {
            $propertyMapping = $this->classMetadata->propertyMappings[$prop];
            $readFieldCount = $propertyMapping->getInputValuesCount();

            $currentInputValues = array_slice($values, $currentIndex, $readFieldCount);
            $currentIndex += $readFieldCount;

            $value = $propertyMapping->convertInputValuesToProp($gateway, $currentInputValues);

            if ($value === null) {
                return null;
            }

            $id[$prop] = $value;
        }

        return $gateway->getReference($className, $id);
    }

    /**
     * @todo use Gateway::getIdentity() instead; currently does not check that the object has an identity
     *
     * @psalm-suppress MixedArrayAccess Psalm does not understand references
     * @psalm-suppress MixedArrayAssignment Psalm does not understand references
     *
     * {@inheritdoc}
     */
    public function convertPropToFields(mixed $propValue) : array
    {
        $result = [];

        /** @var object|null $entity */
        $entity = $propValue;

        if ($this->classMetadata->discriminatorColumn !== null) {
            if ($entity === null) {
                $result[] = ['NULL'];
            } else {
                $class = get_class($entity);
                $discriminatorValue = $this->classMetadata->inverseDiscriminatorMap[$class];
                $result[] = ['?', $discriminatorValue];
            }
        }

        $idProperties = $this->classMetadata->idProperties;

        $identity = [];

        if ($entity !== null) {
            /** @psalm-suppress PossiblyInvalidFunctionCall bindTo() should never return false here */
            (function() use ($idProperties, & $identity) {
                foreach ($idProperties as $prop) {
                    $identity[$prop] = $this->{$prop};
                }
            })->bindTo($entity, $entity)();
        } else {
            foreach ($idProperties as $prop) {
                $identity[$prop] = null;
            }
        }

        foreach ($idProperties as $prop) {
            $propertyMapping = $this->classMetadata->propertyMappings[$prop];

            foreach ($propertyMapping->convertPropToFields($identity[$prop]) as $expressionAndValues) {
                $result[] = $expressionAndValues;
            }
        }

        return $result;
    }
}
