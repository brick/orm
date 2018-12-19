<?php

declare(strict_types=1);

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\ClassMetadata;
use Brick\ORM\PropertyMapping;
use Brick\ORM\Gateway;

/**
 * @internal
 */
class EntityMapping implements PropertyMapping
{
    /**
     * The class metadata of the target entity.
     *
     * @var ClassMetadata
     */
    public $classMetadata;

    /**
     * @var string
     */
    public $fieldNamePrefix;

    /**
     * @var bool
     */
    public $isNullable;

    /**
     * @param ClassMetadata $classMetadata   The target entity class name.
     * @param string        $fieldNamePrefix The prefix for field names.
     * @param bool          $isNullable      Whether the property is nullable.
     */
    public function __construct(ClassMetadata $classMetadata, string $fieldNamePrefix, bool $isNullable)
    {
        $this->classMetadata   = $classMetadata;
        $this->fieldNamePrefix = $fieldNamePrefix;
        $this->isNullable      = $isNullable;
    }

    /**
     * {@inheritdoc}
     */
    public function getType() : string
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

        foreach ($this->classMetadata->idProperties as $prop) {
            foreach ($this->classMetadata->propertyMappings[$prop]->getFieldNames() as $name) {
                $names[] = $this->fieldNamePrefix . $name;
            }
        }

        return $names;
    }

    /**
     * @todo quick&dirty; precompute for better performance
     *
     * {@inheritdoc}
     */
    public function getInputValuesCount(): int
    {
        return count($this->getFieldToInputValuesSQL($this->getFieldNames()));
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
    public function convertInputValuesToProp(Gateway $gateway, array $values)
    {
        $id = [];
        $index = 0;

        foreach ($this->classMetadata->idProperties as $idProperty) {
            $id[$idProperty] = $values[$index++];
        }

        return $gateway->getProxy($this->classMetadata->className, $id);
    }

    /**
     * @todo precompute for better performance
     *
     * {@inheritdoc}
     */
    public function getOutputValuesToFieldSQL() : array
    {
        $result = [];

        foreach ($this->classMetadata->idProperties as $prop) {
            foreach ($this->classMetadata->propertyMappings[$prop]->getOutputValuesToFieldSQL() as $sql) {
                $result[] = $sql;
            }
        }

        return $result;
    }

    /**
     * @todo use Gateway::getIdentity() instead; currently does not check that the object has an identity
     *
     * {@inheritdoc}
     */
    public function convertPropToOutputValues($propValue) : array
    {
        $entity = $propValue;
        $r = new \ReflectionObject($entity);

        $fieldValues = [];

        foreach ($this->classMetadata->idProperties as $prop) {
            $p = $r->getProperty($prop);
            $p->setAccessible(true);
            $idPropValue = $p->getValue($entity);

            foreach ($this->classMetadata->propertyMappings[$prop]->convertPropToOutputValues($idPropValue) as $fieldValue) {
                $fieldValues[] = $fieldValue;
            }
        }

        return $fieldValues;
    }

    /**
     * @todo use Gateway::getIdentity() instead; currently does not check that the object has an identity
     *
     * {@inheritdoc}
     */
    public function convertPropToFields($propValue) : array
    {
        $result = [];

        $entity = $propValue;
        $r = new \ReflectionObject($entity);

        foreach ($this->classMetadata->idProperties as $prop) {
            if ($entity === null) {
                $idPropValue = null;
            } else {
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
