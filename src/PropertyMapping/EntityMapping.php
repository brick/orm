<?php

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
    public function getFieldCount() : int
    {
        $count = 0;

        foreach ($this->classMetadata->idProperties as $prop) {
            $count += $this->classMetadata->properties[$prop]->getFieldCount();
        }

        return $count;
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
     * @todo use Gateway::getIdentity() instead; currently does not check that the object has an identity
     *
     * {@inheritdoc}
     */
    public function propToFields($propValue) : array
    {
        $entity = $propValue;
        $r = new \ReflectionObject($entity);

        $fieldValues = [];

        foreach ($this->classMetadata->idProperties as $prop) {
            $p = $r->getProperty($prop);
            $p->setAccessible(true);
            $idPropValue = $p->getValue($entity);

            foreach ($this->classMetadata->properties[$prop]->propToFields($idPropValue) as $fieldValue) {
                $fieldValues[] = $fieldValue;
            }
        }

        return $fieldValues;
    }

    /**
     * {@inheritdoc}
     */
    public function fieldsToProp(Gateway $gateway, array $fieldValues)
    {
        $id = [];
        $index = 0;

        foreach ($this->classMetadata->idProperties as $idProperty) {
            $id[$idProperty] = $fieldValues[$index++];
        }

        return $gateway->getProxy($this->classMetadata->className, $id);
    }
}
