<?php

declare(strict_types=1);

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\ClassMetadata;
use Brick\ORM\EmbeddableMetadata;
use Brick\ORM\ObjectFactory;
use Brick\ORM\PropertyMapping;
use Brick\ORM\Gateway;

/**
 * @internal
 */
class EmbeddableMapping extends EntityMapping
{
    /**
     * The class metadata of the target entity.
     *
     * @var EmbeddableMetadata
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

        foreach ($this->classMetadata->properties as $prop) {
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

    /**
     * {@inheritdoc}
     */
    public function convertInputValuesToProp(Gateway $gateway, array $values)
    {
        $propValues = [];

        foreach ($this->classMetadata->properties as $index => $property) {
            $propValues[$property] = $values[$index];
        }

        $objectFactory = new ObjectFactory();

        // no need to unset persistent any props here, as we're always loading all values in the embeddable
        $object = $objectFactory->instantiate($this->classMetadata->className, []);
        $objectFactory->write($object, $propValues);

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function convertPropToFields($propValue) : array
    {
        $result = [];

        $entity = $propValue;
        $r = new \ReflectionObject($entity);

        foreach ($this->classMetadata->properties as $prop) {
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
