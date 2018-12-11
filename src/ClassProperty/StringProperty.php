<?php

namespace Brick\ORM\ClassProperty;

/**
 * @internal
 */
class StringProperty extends BuiltinProperty
{
    /**
     * {@inheritdoc}
     */
    public function propToFields($propValue) : array
    {
        if ($propValue === null) {
            return [null];
        }

        return [(string) $propValue];
    }

    /**
     * {@inheritdoc}
     */
    public function fieldsToProp(array $fieldValues)
    {
        if ($fieldValues[0] === null) {
            return null;
        }

        return (string) $fieldValues[0];
    }
}
