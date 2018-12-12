<?php

declare(strict_types=1);

namespace Brick\ORM;

/**
 * Maps a property to database fields.
 */
interface PropertyMapping
{
    /**
     * Returns the list of database field names required to compute the property value.
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
     * Returns the SQL to read each database value required to compute the property value.
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
     * Returns the SQL to send values returned by convertPropToOutputValues() to the database fields.
     *
     * The result array must contain exactly one string for each field returned by getFieldNames(), in the same order.
     * It must contain exactly one question mark placeholder per value returned by convertPropToOutputValues(), in the
     * same order.
     *
     * This method may return more than one placeholder per database field, if a SQL function has to be called to merge
     * two or more values into a single field; for example, sending a Geometry could require to send 2 database values
     * (WKT and SRID) and merge them into a single field using ST_GeomFromText(?, ?).
     *
     * If no transformation is required, this method should return an array containing as many question marks as there
     * are fields in getFieldNames(). For example, if getFieldNames() returns 2 fields, it should return ['?', '?'].
     *
     * @return string[] The list of SQL placeholders, optionally wrapped with SQL code.
     */
    public function getOutputValuesToFieldSQL() : array;

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
    public function convertInputValuesToProp(Gateway $gateway, array $values);

    /**
     * Converts the given property value to database values.
     *
     * The result array must contain exactly one value for each question mark placeholder returned by
     * getOutputValuesToFieldSQL(), in the same order.
     *
     * Each value in the array must be of one of the native PHP types accepted by PreparedStatement::execute().
     *
     * @param mixed $propValue The property value.
     *
     * @return mixed[] The list of database values.
     */
    public function convertPropToOutputValues($propValue) : array;
}
