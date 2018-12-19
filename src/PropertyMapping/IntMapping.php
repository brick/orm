<?php

declare(strict_types=1);

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\Gateway;

/**
 * @internal
 */
class IntMapping extends BuiltinTypeMapping
{
    /**
     * {@inheritdoc}
     */
    public function getType() : string
    {
        return 'int';
    }

    /**
     * {@inheritdoc}
     */
    public function convertPropToOutputValues($propValue) : array
    {
        if ($propValue === null) {
            return [null];
        }

        return [(int) $propValue];
    }

    /**
     * {@inheritdoc}
     */
    public function convertInputValuesToProp(Gateway $gateway, array $values)
    {
        if ($values[0] === null) {
            return null;
        }

        return (int) $values[0];
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
            ['?', (int) $propValue]
        ];
    }
}
