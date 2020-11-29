<?php

declare(strict_types=1);

namespace Brick\ORM\PropertyMapping;

use Brick\ORM\Gateway;

/**
 * @internal
 */
class IntMapping extends BuiltinTypeMapping
{
    public function getType() : string|null
    {
        return 'int';
    }

    public function convertInputValuesToProp(Gateway $gateway, array $values) : mixed
    {
        if ($values[0] === null) {
            return null;
        }

        return (int) $values[0];
    }

    public function convertPropToFields(mixed $propValue) : array
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
