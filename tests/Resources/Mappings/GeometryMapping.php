<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Mappings;

use Brick\ORM\PropertyMapping;
use Brick\ORM\Gateway;
use Brick\ORM\Tests\Resources\Objects\Geometry;

/**
 * @internal
 */
class GeometryMapping implements PropertyMapping
{
    protected string $fieldName;

    protected bool $isNullable;

    /**
     * @param string $fieldName  The field name.
     * @param bool   $isNullable Whether the property is nullable.
     */
    public function __construct(string $fieldName, bool $isNullable)
    {
        $this->fieldName  = $fieldName;
        $this->isNullable = $isNullable;
    }

    public function getType() : ?string
    {
        return Geometry::class;
    }

    public function isNullable() : bool
    {
        return $this->isNullable;
    }

    public function getFieldNames(): array
    {
        return [$this->fieldName];
    }

    public function getInputValuesCount() : int
    {
        return 2;
    }

    public function getFieldToInputValuesSQL(array $fieldNames) : array
    {
        return [
            'ST_AsText(' . $fieldNames[0] . ')',
            'ST_SRID(' . $fieldNames[0] . ')'
        ];
    }

    public function convertInputValuesToProp(Gateway $gateway, array $values) : mixed
    {
        [$wkt, $srid] = $values;

        if ($wkt === null) {
            return null;
        }

        return new Geometry($wkt, (int) $srid);
    }

    public function convertPropToFields(mixed $propValue) : array
    {
        if ($propValue === null) {
            return [
                ['NULL']
            ];
        }

        /** @var Geometry $propValue */
        return [
            ['ST_GeomFromText(?, ?)', $propValue->getWKT(), $propValue->getSRID()]
        ];
    }
}
