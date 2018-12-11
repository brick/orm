<?php

namespace Brick\ORM;

interface ClassProperty
{
    /**
     * Returns the number of database fields required to compute the property value.
     *
     * This must be one or more.
     *
     * @return int
     */
    public function getFieldCount() : int;

    /**
     * Returns database field names required to compute the property value.
     *
     * The number of fields must be equal to the number returned by getFieldCount().
     *
     * @return string[]
     */
    public function getFieldNames() : array;

    /**
     * Returns the database field value(s) for the given property value.
     *
     * The number of values in the result array must be equal to the number returned by getFieldCount().
     * Each value must be of one of the native PHP types accepted by the PreparedStatement::execute() method.
     *
     * @param mixed $propValue The property value.
     *
     * @return mixed[] A numeric array of database field values.
     */
    public function propToFields($propValue) : array;

    /**
     * Returns the property value for the given database field values.
     *
     * The number of values of the input array must be equal to the number returned by getFieldCount().
     * Each value must be of one of the native PHP types accepted by the PreparedStatement::execute() method.
     *
     * @param mixed[] $fieldValues A numeric array of database field values.
     *
     * @return mixed The property value.
     */
    public function fieldsToProp(array $fieldValues);
}
