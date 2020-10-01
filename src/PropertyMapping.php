<?php

declare(strict_types=1);

namespace Brick\ORM;

/**
 * Maps a property to one or more database fields.
 */
interface PropertyMapping
{
    /**
     * Returns the PHP type of the property, or null if the mapping can handle mixed types (such as JSON columns).
     *
     * @return string
     */
    public function getType() : ?string;

    /**
     * Returns whether the property is nullable.
     *
     * @return bool
     */
    public function isNullable() : bool;

    /**
     * Returns the list of database field names the property maps to.
     *
     * @return string[]
     */
    public function getFieldNames() : array;

    /**
     * Returns the number of database values required to compute the property value.
     *
     * This must return the number entries returned by getFieldToInputValuesSQL().
     *
     * @return int
     */
    public function getInputValuesCount() : int;

    /**
     * Returns the SQL expressions to read each database value required to compute the property value.
     *
     * The input array will contain exactly one string for each field returned by getFieldNames().
     * It will contain these field names, in the same order, with possible prefixes and quoting.
     *
     * The result array must contain exactly one string for each value required by convertInputValuesToProp(), in the
     * same order.
     *
     * This method may return more values that the number of field names, if several SQL functions have to be called on
     * a single field to load the property; for example, loading a Geometry could require selecting both ST_AsText()
     * and ST_SRID() on a single field.
     *
     * If no transformation is required, this method should return the input parameter unchanged.
     *
     * @param string[] $fieldNames The field names.
     *
     * @return string[] The list of fields to read, optionally wrapped with SQL code.
     */
    public function getFieldToInputValuesSQL(array $fieldNames) : array;

    /**
     * Converts the given database values to a property value.
     *
     * The input array will contain one value for each getFieldToInputValuesSQL() entry, in the same order.
     *
     * @param Gateway $gateway
     * @param mixed[] $values The list of database values.
     *
     * @return mixed The property value.
     */
    public function convertInputValuesToProp(Gateway $gateway, array $values) : mixed;

    /**
     * Converts the given property to SQL expressions and values for each database field it is mapped to.
     *
     * The result array must contain exactly one entry for each field returned by getFieldNames(), in the same order.
     * Each entry must be a numeric array whose first entry is a string containing an SQL expression, and whose further
     * entries are values to be bound for each question mark placeholder the SQL expression contains.
     *
     * Example for a simple scalar value mapping:
     * [
     *     ['?', $value]
     * ]
     *
     * Example for a geometry object, mapping 2 values to a single field:
     * [
     *     ['ST_GeomFromText(?, ?)', $wkt, $srid]
     * ]
     *
     * Example for a complex property mapping many values to 4 fields:
     * [
     *     ['NULL'],
     *     ['?', $value1],
     *     ['?', $value2],
     *     ['ST_GeomFromText(?, ?)', $wkt, $srid]
     * ]
     *
     * @param mixed $propValue The property value.
     *
     * @return array
     */
    public function convertPropToFields(mixed $propValue) : array;
}
