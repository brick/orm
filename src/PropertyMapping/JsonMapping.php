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

    public function __construct(string $fieldName, bool $isNullable, bool $objectAsArray)
    {
        $this->fieldName = $fieldName;
        $this->isNullable = $isNullable;
        $this->objectAsArray = $objectAsArray;
    }

    public function isNullable() : bool
    {
        return $this->isNullable;
    }

    public function getFieldNames() : array
    {
        return [$this->fieldName];
    }

    public function getInputValuesCount() : int
    {
        return 1;
    }

    public function getFieldToInputValuesSQL(array $fieldNames) : array
    {
        return $fieldNames;
    }

    public function getType() : string|null
    {
        return null;
    }

    public function convertInputValuesToProp(Gateway $gateway, array $values) : mixed
    {
        /** @var array{string|null} $values */

        if ($values[0] === null) {
            return null;
        }

        return json_decode($values[0], $this->objectAsArray);
    }

    public function convertPropToFields(mixed $propValue) : array
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
