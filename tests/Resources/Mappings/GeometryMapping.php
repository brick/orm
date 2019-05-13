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
    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var bool
     */
    protected $isNullable;

    /**
     * @param string $fieldName  The field name.
     * @param bool   $isNullable Whether the property is nullable.
     */
    public function __construct(string $fieldName, bool $isNullable)
    {
        $this->fieldName  = $fieldName;
        $this->isNullable = $isNullable;
    }

    /**
     * {@inheritdoc}
     */
    public function getType() : ?string
    {
        return Geometry::class;
    }

    /**
     * {@inheritdoc}
     */
    public function isNullable() : bool
    {
        return $this->isNullable;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldNames(): array
    {
        return [$this->fieldName];
    }

    /**
     * {@inheritdoc}
     */
    public function getInputValuesCount() : int
    {
        return 2;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldToInputValuesSQL(array $fieldNames) : array
    {
        return [
            'ST_AsText(' . $fieldNames[0] . ')',
            'ST_SRID(' . $fieldNames[0] . ')'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function convertInputValuesToProp(Gateway $gateway, array $values)
    {
        [$wkt, $srid] = $values;

        if ($wkt === null) {
            return null;
        }

        return new Geometry($wkt, (int) $srid);
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

        /** @var Geometry $propValue */
        return [
            ['ST_GeomFromText(?, ?)', $propValue->getWKT(), $propValue->getSRID()]
        ];
    }
}
