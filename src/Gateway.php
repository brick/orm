<?php

declare(strict_types=1);

namespace Brick\ORM;

use Brick\Db\Connection;

/**
 * The underlying table data gateway for repositories.
 *
 * This is a low-level class that assumes some level of validation in call parameters, and can fail badly with obscure
 * error messages when called with improper parameters. This class is not part of the public API and may change at any
 * time. Use the generated repositories instead. You have been warned.
 *
 * Current limitations:
 * - Before PHP 7.4: update() will ignore null fields, even if they've been explicitly nulled out after load()
 * - No support for private properties (for simplicity, performance, ease of requesting property names in load(), and lazy initialization proxies)
 * - No support for update() on mutated identities: explicitly remove() the previous identity then add() the new one
 *
 * @internal
 */
class Gateway
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * The class metadata, indexed by class name.
     *
     * @var ClassMetadata[]
     */
    private $classMetadata;

    /**
     * @var ObjectFactory
     */
    private $objectFactory;

    /**
     * Gateway constructor.
     *
     * @param Connection      $connection
     * @param ClassMetadata[] $classMetadata
     */
    public function __construct(Connection $connection, array $classMetadata)
    {
        $this->connection = $connection;
        $this->classMetadata = $classMetadata;
        $this->objectFactory = new ObjectFactory();
    }

    /**
     * @param string   $table           The table to select from.
     * @param string[] $selectFields    The list of field names to select.
     * @param string[] $whereConditions The list of 'key = value' conditions.
     * @param int      $lockMode        The lock mode, as a LockMode constant.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    private function getSelectSQL(string $table, array $selectFields, array $whereConditions, int $lockMode) : string
    {
        $selectFields = implode(', ', $selectFields);
        $whereConditions = implode(' AND ', $whereConditions);

        $query = sprintf('SELECT %s FROM %s WHERE %s', $selectFields, $table, $whereConditions);

        // @todo MySQL / PostgreSQL only
        switch ($lockMode) {
            case LockMode::NONE:
                break;

            case LockMode::READ:
                $query .= ' FOR SHARE';
                break;

            case LockMode::WRITE:
                $query .= ' FOR UPDATE';
                break;

            default:
                throw new \InvalidArgumentException('Invalid lock mode.');
        }

        return $query;
    }

    /**
     * Builds an INSERT query.
     *
     * The arrays of fields and values must have the same number of elements.
     *
     * @param string   $table  The table name.
     * @param string[] $fields The list of field names.
     * @param string[] $values The list of placeheld field values.
     *
     * @return string
     */
    private function getInsertSQL(string $table, array $fields, array $values) : string
    {
        $fields = implode(', ', $fields);
        $values = implode(', ', $values);

        return sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, $fields, $values);
    }

    /**
     * @param string $table           The table name.
     * @param array  $updates         The list of 'key = value' pairs to update.
     * @param array  $whereConditions The list of 'key = value' WHERE conditions.
     *
     * @return string
     */
    private function getUpdateSQL(string $table, array $updates, array $whereConditions) : string
    {
        $updates = implode(', ', $updates);
        $whereConditions = implode(' AND ', $whereConditions);

        return sprintf('UPDATE %s SET %s WHERE %s', $table, $updates, $whereConditions);
    }

    /**
     * @param string $table           The table name.
     * @param array  $whereConditions The list of 'key = value' WHERE conditions.
     *
     * @return string
     */
    private function getDeleteSQL(string $table, array $whereConditions) : string
    {
        $whereConditions = implode(' AND ', $whereConditions);

        return sprintf('DELETE FROM %s WHERE %s', $table, $whereConditions);
    }

    /**
     * Loads the entity with the given identity from the database.
     *
     * An optional array of property names can be provided, to load a partial object. By default, all properties will be
     * loaded and set. Note that properties part of the identity will always be set, regardless of whether they are part
     * of this array or not. If an empty array is provided, only the properties part of the identity will be set.
     *
     * @param string        $class    The entity class name.
     * @param array         $id       The identity, as a map of property name to value.
     * @param string[]|null $props    An optional array of property names to load.
     * @param int           $lockMode The lock mode.
     *
     * @return object|null The entity, or null if it doesn't exist.
     *
     * @throws \RuntimeException If a property name does not exist.
     */
    public function load(string $class, array $id, ?array $props, int $lockMode) : ?object
    {
        $propValues = $this->loadProps($class, $id, $props, $lockMode);

        if ($propValues === null) {
            return null;
        }

        $classMetadata = $this->classMetadata[$class];

        $object = $this->objectFactory->instantiate($class, $classMetadata->properties);
        $this->objectFactory->write($object, $propValues + $id);

        return $object;
    }

    /**
     * Loads an entity's properties.
     *
     * @param string        $class    The entity class name.
     * @param array         $id       The identity, as a map of property name to value.
     * @param string[]|null $props    An optional array of property names to load. Defaults to non-id properties.
     * @param int           $lockMode The lock mode.
     *
     * @return array|null The properties, or null if the entity doesn't exist.
     *
     * @throws \RuntimeException If a property name does not exist.
     */
    public function loadProps(string $class, array $id, ?array $props, int $lockMode) : ?array
    {
        $classMetadata = $this->classMetadata[$class];

        if ($props === null) {
            $props = $classMetadata->nonIdProperties;
        } else {
            $props = array_values(array_unique($props));

            foreach ($props as $prop) {
                if (! isset($classMetadata->propertyMappings[$prop])) {
                    // @todo UnknownPropertyException
                    throw new \RuntimeException(sprintf('The %s::$%s property does not exist.', $class, $prop));
                }
            }
        }

        $selectFields = [];

        foreach ($props as $prop) {
            $propertyMapping = $classMetadata->propertyMappings[$prop];

            // @todo quote field names
            $fieldNames = $propertyMapping->getFieldNames();
            $fieldToInputValuesSQL = $propertyMapping->getFieldToInputValuesSQL($fieldNames);

            foreach ($fieldToInputValuesSQL as $selectField) {
                $selectFields[] = $selectField;
            }
        }

        $whereConditions = [];
        $outputValues = [];

        foreach ($id as $prop => $value) {
            $propertyMapping = $classMetadata->propertyMappings[$prop];
            $valuesToFieldSQL = $propertyMapping->getOutputValuesToFieldSQL();

            foreach ($propertyMapping->getFieldNames() as $index => $fieldName) { // @todo quote field name
                $whereConditions[] = $fieldName . ' = ' . $valuesToFieldSQL[$index];
            }

            foreach ($propertyMapping->convertPropToOutputValues($value) as $outputValue) {
                $outputValues[] = $outputValue;
            }
        }

        if (! $selectFields) {
            // no props requested, just perform a SELECT 1
            $selectFields = ['1'];
        }

        $sql = $this->getSelectSQL($classMetadata->tableName, $selectFields, $whereConditions, $lockMode);
        $statement = $this->connection->prepare($sql);
        $statement->execute($outputValues);

        $inputValues = $statement->fetch();

        if ($inputValues === null) {
            return null;
        }

        $index = 0;
        $propValues = [];

        foreach ($props as $prop) {
            $propertyMapping = $classMetadata->propertyMappings[$prop];
            $valuesCount = $propertyMapping->getInputValuesCount();

            $propInputValues = array_slice($inputValues, $index, $valuesCount);
            $index += $valuesCount;

            $propValues[$prop] = $propertyMapping->convertInputValuesToProp($this, $propInputValues);
        }

        return $propValues;
    }

    /**
     * Returns a placeholder for the entity with the given identity.
     *
     * This method may be used when an object is required, but only its identity actually matters. For example, when
     * loading an entity whose identity is composed of other objects, and the identity of these objects is known, but
     * these objects do not need to be actually loaded from the database.
     *
     * No check is performed to see if the entity actually exists in the database.
     * Only properties part of the identity will be set.
     *
     * @param string $class The entity class name.
     * @param array  $id    The identity, as a map of property name to value.
     *
     * @return object
     */
    public function getPlaceholder(string $class, array $id) : object
    {
        $classMetadata = $this->classMetadata[$class];

        $entity = $this->objectFactory->instantiate($class, $classMetadata->properties);
        $this->objectFactory->write($entity, $id);

        return $entity;
    }

    /**
     * @param string $class
     * @param array  $id
     *
     * @return object
     */
    public function getProxy(string $class, array $id) : object
    {
        $proxyClass = $this->classMetadata[$class]->proxyClassName;

        return new $proxyClass($this, $id);
    }

    /**
     * Returns whether the given entity exists in the database.
     *
     * @param string $class  The entity class name.
     * @param object $entity The entity to check.
     *
     * @return bool
     */
    public function exists(string $class, object $entity) : bool
    {
        return $this->existsIdentity($class, $this->getIdentity($class, $entity));
    }

    /**
     * Returns whether an entity with the given identity exists in the database.
     *
     * @todo faster implementation
     *
     * @param string $class The entity class name.
     * @param array  $id    The identity, as a map of property name to value.
     *
     * @return bool
     */
    public function existsIdentity(string $class, array $id) : bool
    {
        return $this->load($class, $id, [], LockMode::NONE) !== null;
    }

    /**
     * Saves the given entity to the database.
     *
     * This results in an immediate INSERT statement being executed against the database.
     *
     * @param string $class  The entity class name.
     * @param object $entity The entity to save.
     *
     * @return void
     *
     * @throws \RuntimeException
     * @throws \Brick\Db\DbException If a database error occurs.
     */
    public function save(string $class, object $entity) : void
    {
        $classMetadata = $this->classMetadata[$class];

        $propValues = $this->objectFactory->read($entity);

        if ($classMetadata->isAutoIncrement) {
            foreach ($classMetadata->idProperties as $idProperty) {
                if (isset($propValues[$idProperty])) {
                    // @todo custom exception
                    throw new \RuntimeException('Cannot save() an entity with an autoincrement identity already set.');
                }
            }
        } else {
            foreach ($classMetadata->idProperties as $idProperty) {
                if (! isset($propValues[$idProperty])) {
                    // @todo custom exception
                    throw new \RuntimeException('Cannot save() an entity with a non-autoincrement identity not set.');
                }
            }
        }

        $fieldNames = [];
        $fieldValues = [];
        $outputValues = [];

        foreach ($propValues as $prop => $value) {
            if (! isset($classMetadata->propertyMappings[$prop])) {
                // Non-persistent property
                continue;
            }

            $propertyMapping = $classMetadata->propertyMappings[$prop];

            $valuesToFieldSQL = $propertyMapping->getOutputValuesToFieldSQL();

            foreach ($propertyMapping->getFieldNames() as $index => $fieldName) { // @todo quote field name
                $fieldNames[] = $fieldName;
                $fieldValues[] = $valuesToFieldSQL[$index];
            }

            foreach ($propertyMapping->convertPropToOutputValues($value) as $outputValue) {
                $outputValues[] = $outputValue;
            }
        }

        $sql = $this->getInsertSQL($classMetadata->tableName, $fieldNames, $fieldValues);
        $statement = $this->connection->prepare($sql);
        $statement->execute($outputValues);

        // Set the identity property if the table is auto-increment
        if ($classMetadata->isAutoIncrement) {
            $lastInsertId = $this->connection->lastInsertId();

            // Note: can only be a single property mapping to a single field, using a single scalar value
            $prop = $classMetadata->idProperties[0];
            $value = $classMetadata->propertyMappings[$prop]->convertInputValuesToProp($this, [$lastInsertId]);

            $this->objectFactory->write($entity, [$prop => $value]);
        }
    }

    /**
     * Updates the given entity in the database.
     *
     * This results in an immediate UPDATE statement being executed against the database.
     *
     * @param string $class  The entity class name.
     * @param object $entity The entity to update.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function update(string $class, object $entity) : void
    {
        $classMetadata = $this->classMetadata[$class];

        $propValues = $this->objectFactory->read($entity);

        foreach ($classMetadata->idProperties as $idProperty) {
            if (! isset($propValues[$idProperty])) {
                // @todo custom exception
                throw new \RuntimeException('Cannot update() an entity with no identity.');
            }
        }

        $updates = [];
        $whereConditions = [];
        $outputValues = [];

        foreach ($classMetadata->nonIdProperties as $prop) {
            if (isset($propValues[$prop])) {
                $propertyMapping = $classMetadata->propertyMappings[$prop];
                $valuesToFieldSQL = $propertyMapping->getOutputValuesToFieldSQL();

                foreach ($propertyMapping->getFieldNames() as $index => $fieldName) { // @todo quote field name
                    $updates[] = $fieldName . ' = ' . $valuesToFieldSQL[$index];
                }

                foreach ($propertyMapping->convertPropToOutputValues($propValues[$prop]) as $outputValue) {
                    $outputValues[] = $outputValue;
                }
            }
        }

        foreach ($classMetadata->idProperties as $prop) {
            $propertyMapping = $classMetadata->propertyMappings[$prop];
            $valuesToFieldSQL = $propertyMapping->getOutputValuesToFieldSQL();

            foreach ($propertyMapping->getFieldNames() as $index => $fieldName) {
                $whereConditions[] = $fieldName . ' = ' . $valuesToFieldSQL[$index];
            }

            foreach ($propertyMapping->convertPropToOutputValues($propValues[$prop]) as $outputValue) {
                $outputValues[] = $outputValue;
            }
        }

        $sql = $this->getUpdateSQL($classMetadata->tableName, $updates, $whereConditions);
        $statement = $this->connection->prepare($sql);
        $statement->execute($outputValues);
    }

    /**
     * Removes the given entity from the database.
     *
     * This results in an immediate DELETE statement being executed against the database.
     *
     * @param string $class  The entity class name.
     * @param object $entity The entity to remove.
     *
     * @return void
     */
    public function remove(string $class, object $entity) : void
    {
        $this->removeIdentity($class, $this->getIdentity($class, $entity));
    }

    /**
     * Removes the User with the given identity from the database.
     *
     * This results in an immediate DELETE statement being executed against the database.
     *
     * @param string $class The entity class name.
     * @param array  $id    The identity, as a map of property name to value.
     *
     * @return void
     */
    public function removeIdentity(string $class, array $id) : void
    {
        $classMetadata = $this->classMetadata[$class];

        $whereConditions = [];
        $outputValues = [];

        foreach ($id as $prop => $value) {
            $propertyMapping = $classMetadata->propertyMappings[$prop];
            $valuesToFieldSQL = $propertyMapping->getOutputValuesToFieldSQL();

            foreach ($propertyMapping->getFieldNames() as $index => $fieldName) { // @todo quote field name
                $whereConditions[] = $fieldName . ' = ' . $valuesToFieldSQL[$index];
            }

            foreach ($propertyMapping->convertPropToOutputValues($value) as $outputValue) {
                $outputValues[] = $outputValue;
            }
        }

        $sql = $this->getDeleteSQL($classMetadata->tableName, $whereConditions);
        $statement = $this->connection->prepare($sql);
        $statement->execute($outputValues);
    }

    /**
     * @param string $class  The entity class name.
     * @param object $entity The entity.
     *
     * @return array The identity, as a map of property name to value.
     *
     * @throws \RuntimeException
     */
    private function getIdentity(string $class, object $entity) : array
    {
        $classMetadata = $this->classMetadata[$class];
        $values = $this->objectFactory->read($entity);

        $identity = [];

        foreach ($classMetadata->idProperties as $idProperty) {
            if (! isset($values[$idProperty])) {
                throw new \RuntimeException('The entity has no identity.');
            }

            $identity[$idProperty] = $values[$idProperty];
        }

        return $identity;
    }
}
