<?php

namespace Brick\ORM\ClassProperty;

use Brick\ORM\ClassProperty;

/**
 * @internal
 */
abstract class BuiltinProperty implements ClassProperty
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
