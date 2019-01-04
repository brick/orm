<?php

declare(strict_types=1);

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\EntityMetadata;
use Brick\ORM\LockMode;
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
     * @var EntityMetadata
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
     * @todo precompute for better performance
     *
     * {@inheritdoc}
     */
    public function getInputValuesCount() : int
    {
        $count = 0;

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
        $currentIndex = 0;

        $id = [];

        foreach ($this->classMetadata->idProperties as $prop) {
            $propertyMapping = $this->classMetadata->propertyMappings[$prop];
            $readFieldCount = $propertyMapping->getInputValuesCount();

            $currentInputValues = array_slice($values, $currentIndex, $readFieldCount);
            $currentIndex += $readFieldCount;

            $id[$prop] = $propertyMapping->convertInputValuesToProp($gateway, $currentInputValues);
        }

        if ($this->classMetadata->childClasses) {
            // Not a leaf entity: eager load
            // @todo couldn't we JOIN the target table to get just the discriminator value?
            //       in any case, we should JOIN to eager load, not perform another query!
            return $gateway->load($this->classMetadata->className, $id, LockMode::NONE, null);
        }

        return $gateway->getProxy($this->classMetadata->className, $id);
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

        if ($entity !== null) {
            $r = new \ReflectionObject($entity);
        }

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
