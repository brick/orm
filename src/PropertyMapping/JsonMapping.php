<?php

declare(strict_types=1);

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\Gateway;
use Brick\ORM\PropertyMapping;

/**
 * Maps a column of any type to a JSON datatype in the database.
 */
class JsonMapping implements PropertyMapping
{
    public string $fieldName;

    public bool $isNullable;

    /**
     * Whether to decode JSON objects as associative arrays (true) or stdClass objects (false).
     */
    public bool $objectAsArray;

    /**
     * @param string $fieldName
     * @param bool   $isNullable
     * @param bool   $objectAsArray
     */
    public function __construct(string $fieldName, bool $isNullable, bool $objectAsArray)
    {
        $this->fieldName = $fieldName;
        $this->isNullable = $isNullable;
        $this->objectAsArray = $objectAsArray;
    }

    /**
     * {@inheritdoc}
     */
    public function isNullable() : bool
    {
        return $this->isNullable;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldNames() : array
    {
        return [$this->fieldName];
    }

    /**
     * {@inheritdoc}
     */
    public function getInputValuesCount() : int
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldToInputValuesSQL(array $fieldNames) : array
    {
        return $fieldNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getType() : ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function convertInputValuesToProp(Gateway $gateway, array $values)
    {
        if ($values[0] === null) {
            return null;
        }

        return json_decode($values[0], $this->objectAsArray);
    }

    /**
     * {@inheritdoc}
     */
    public function convertPropToFields($propValue) : array
    {
        if ($propValue === null) {
            return [
                ['NULL']
            ];
        }

        return [
            ['?', json_encode($propValue)]
        ];
    }
}
