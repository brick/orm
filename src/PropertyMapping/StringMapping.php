<?php

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\Gateway;

/**
 * @internal
 */
class StringMapping extends BuiltinTypeMapping
{
    /**
     * {@inheritdoc}
     */
    public function convertPropToOutputValues($propValue) : array
    {
        if ($propValue === null) {
            return [null];
        }

        return [(string) $propValue];
    }

    /**
     * {@inheritdoc}
     */
    public function convertInputValuesToProp(Gateway $gateway, array $values)
    {
        if ($values[0] === null) {
            return null;
        }

        return (string) $values[0];
    }
}
