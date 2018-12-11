<?php

namespace Brick\ORM\ClassProperty;

/**
 * @internal
 */
class IntProperty extends BuiltinProperty
{
    /**
     * {@inheritdoc}
     */
    public function propToFields($propValue) : array
    {
        if ($propValue === null) {
            return [null];
        }

        return [(int) $propValue];
    }

    /**
     * {@inheritdoc}
     */
    public function fieldsToProp(array $fieldValues)
    {
        if ($fieldValues[0] === null) {
            return null;
        }

        return (int) $fieldValues[0];
    }
}
