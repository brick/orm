<?php

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\Gateway;

/**
 * @internal
 */
class BoolMapping extends BuiltinTypeMapping
{
    /**
     * {@inheritdoc}
     */
    public function propToFields($propValue) : array
    {
        if ($propValue === null) {
            return [null];
        }

        return [(bool) $propValue];
    }

    /**
     * {@inheritdoc}
     */
    public function fieldsToProp(Gateway $gateway, array $fieldValues)
    {
        if ($fieldValues[0] === null) {
            return null;
        }

        return (bool) $fieldValues[0];
    }
}
