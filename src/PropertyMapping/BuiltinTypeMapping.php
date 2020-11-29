<?php

declare(strict_types=1);

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\PropertyMapping;

/**
 * @internal
 */
abstract class BuiltinTypeMapping implements PropertyMapping
{
    public string $fieldName;

    public bool $isNullable;

    public function __construct(string $fieldName, bool $isNullable)
    {
        $this->fieldName = $fieldName;
        $this->isNullable = $isNullable;
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
}
