<?php

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\PropertyMapping;

/**
 * @internal
 */
abstract class BuiltinTypeMapping implements PropertyMapping
{
    /**
     * @var string
     */
    public $fieldName;

    /**
     * @param string $fieldName
     */
    public function __construct(string $fieldName)
    {
        $this->fieldName = $fieldName;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldCount() : int
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldNames() : array
    {
        return [$this->fieldName];
    }
}
