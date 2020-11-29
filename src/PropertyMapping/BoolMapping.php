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
    public function getType() : string|null
    {
        return 'bool';
    }

    /**
     * {@inheritdoc}
     */
    public function convertInputValuesToProp(Gateway $gateway, array $values) : mixed
    {
        if ($values[0] === null) {
            return null;
        }

        return (bool) $values[0];
    }

    /**
     * {@inheritdoc}
     */
    public function convertPropToFields(mixed $propValue) : array
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
