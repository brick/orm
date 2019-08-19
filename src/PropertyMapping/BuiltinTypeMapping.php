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

    /**
     * @param string $fieldName
     * @param bool   $isNullable
     */
    public function __construct(string $fieldName, bool $isNullable)
    {
        $this->fieldName = $fieldName;
        $this->isNullable = $isNullable;
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
}
