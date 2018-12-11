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
    protected $classMetadata;

    /**
     * @var string
     */
    protected $fieldNamePrefix;

    /**
     * @param ClassMetadata $classMetadata   The target entity class name.
     * @param string        $fieldNamePrefix The prefix for field names.
     */
    public function __construct(ClassMetadata $classMetadata, string $fieldNamePrefix)
    {
        $this->classMetadata   = $classMetadata;
        $this->fieldNamePrefix = $fieldNamePrefix;
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
            foreach ($this->classMetadata->properties[$prop]->getFieldNames() as $name) {
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
            $propertyMapping = $this->classMetadata->properties[$prop];
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
            foreach ($this->classMetadata->properties[$prop]->getOutputValuesToFieldSQL() as $sql) {
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

            foreach ($this->classMetadata->properties[$prop]->convertPropToOutputValues($idPropValue) as $fieldValue) {
                $fieldValues[] = $fieldValue;
            }
        }

        return $fieldValues;
    }
}
