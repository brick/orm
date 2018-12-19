<?php

declare(strict_types=1);

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
    public function getType() : string
    {
        return 'bool';
    }

    /**
     * {@inheritdoc}
     */
    public function convertPropToOutputValues($propValue) : array
    {
        if ($propValue === null) {
            return [null];
        }

        return [(bool) $propValue];
    }

    /**
     * {@inheritdoc}
     */
    public function convertInputValuesToProp(Gateway $gateway, array $values)
    {
        if ($values[0] === null) {
            return null;
        }

        return (bool) $values[0];
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
            ['?', (bool) $propValue]
        ];
    }
}
