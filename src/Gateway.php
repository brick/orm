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
 * - No support for private properties in parent classes
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
     * @param string   $table         The table to select from.
     * @param string[] $selectFields  The list of field names to select.
     * @param string[] $whereFields   The list of field names part of the WHERE clause.
     * @param int      $lockMode      The lock mode, as a LockMode constant.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    private function getSelectSQL(string $table, array $selectFields, array $whereFields, int $lockMode) : string
    {
        foreach ($whereFields as $key => $field) {
            $whereFields[$key] = $field . ' = ?';
        }

        $selectFields = implode(', ', $selectFields);
        $whereFields = implode(' AND ', $whereFields);

        $query = sprintf('SELECT %s FROM %s WHERE %s', $selectFields, $table, $whereFields);

        // @todo MySQL / PostgreSQL only
        switch ($lockMode) {
            case LockMode::NONE:
                break;

            case LockMode::READ:
                $query .= ' FOR SHARE';
                break;

            case LockMode::WRITE:
                $query .= ' FOR UDPATE';
                break;

            default:
                throw new \InvalidArgumentException('Invalid lock mode.');
        }

        return $query;
    }

    /**
     * @param string $table  The table name.
     * @param array  $fields The list of field names.
     *
     * @return string
     */
    private function getInsertSQL(string $table, array $fields) : string
    {
        $values = implode(', ', array_fill(0, count($fields), '?'));
        $fields = implode(', ', $fields);

        return sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, $fields, $values);
    }

    /**
     * @param string $table        The table name.
     * @param array  $updateFields The list of field names to update.
     * @param array  $whereFields  The list of field names part of the WHERE clause.
     *
     * @return string
     */
    private function getUpdateSQL(string $table, array $updateFields, array $whereFields) : string
    {
        foreach ($updateFields as $key => $field) {
            $updateFields[$key] = $field . ' = ?';
        }

        foreach ($whereFields as $key => $field) {
            $whereFields[$key] = $field . ' = ?';
        }

        $updateFields = implode(', ', $updateFields);
        $whereFields = implode(' AND ', $whereFields);

        return sprintf('UPDATE %s SET %s WHERE %s', $table, $updateFields, $whereFields);
    }

    /**
     * @param string $table        The table name.
     * @param array  $whereFields  The list of field names part of the WHERE clause.
     *
     * @return string
     */
    private function getDeleteSQL(string $table, array $whereFields) : string
    {
        foreach ($whereFields as $key => $field) {
            $whereFields[$key] = $field . ' = ?';
        }

        $whereFields = implode(' AND ', $whereFields);

        return sprintf('DELETE FROM %s WHERE %s', $table, $whereFields);
    }

    /**
     * Loads the entity with the given identity from the database.
     *
     * An optional array of property names can be provided, to load a partial object. By default, all properties will be
     * loaded and set. Note that properties part of the identity will always be set, regardless of whether they are part
     * of this array or not. If an empty array is provided, only the properties part of the identity will be set.
     *
     * @param string        $class    The entity class name.
     * @param array         $id       The identity, as an associative array of property name to value.
     * @param string[]|null $props    An optional array of property names to load.
     * @param int           $lockMode The lock mode.
     *
     * @return object|null The entity, or null if it doesn't exist.
     *
     * @throws \RuntimeException If a property name does not exist.
     */
    public function load(string $class, array $id, ?array $props, int $lockMode) : ?object
    {
        $classMetadata = $this->classMetadata[$class];

        if ($props === null) {
            $props = $classMetadata->nonIdProperties;
        } else {
            $props = array_values(array_unique($props));

            foreach ($props as $prop) {
                if (! isset($classMetadata->properties[$prop])) {
                    // @todo UnknownPropertyException
                    throw new \RuntimeException(sprintf('The %s::$%s property does not exist.', $class, $prop));
                }
            }
        }

        $selectFields = [];

        foreach ($props as $prop) {
            foreach ($classMetadata->properties[$prop]->getFieldNames() as $fieldName) {
                $selectFields[] = $fieldName;
            }
        }

        $whereFields = [];
        $whereFieldValues = [];

        foreach ($id as $prop => $value) {
            $classProperty = $classMetadata->properties[$prop];

            foreach ($classProperty->getFieldNames() as $fieldName) {
                $whereFields[] = $fieldName;
            }

            foreach ($classProperty->propToFields($value) as $fieldValue) {
                $whereFieldValues[] = $fieldValue;
            }
        }

        if (! $selectFields) {
            // no props requested, just perform a SELECT 1
            $selectFields = ['1'];
        }

        $sql = $this->getSelectSQL($classMetadata->tableName, $selectFields, $whereFields, $lockMode);
        $statement = $this->connection->prepare($sql);
        $statement->execute($whereFieldValues);

        $fieldValues = $statement->fetch();

        if ($fieldValues === null) {
            return null;
        }

        $entity = $this->objectFactory->instantiate($class, $id);

        $index = 0;
        $propValues = [];

        foreach ($props as $prop) {
            $classProperty = $classMetadata->properties[$prop];
            $fieldCount = $classProperty->getFieldCount();

            $propFieldValues = array_slice($fieldValues, $index, $fieldCount);
            $index += $fieldCount;

            $propValues[$prop] = $classProperty->fieldsToProp($propFieldValues);
        }

        $this->objectFactory->hydrate($entity, $propValues);

        return $entity;
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
     * @param array  $id    The identity, as an associative array of property name to value.
     *
     * @return object
     */
    public function getPlaceholder(string $class, array $id) : object
    {
        return $this->objectFactory->instantiate($class, $id);
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
     * @param array  $id    The identity, as an associative array of property name to value.
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

        $props = array_keys($classMetadata->properties);
        $propValues = $this->objectFactory->read($entity, $props);

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

        // @todo don't not assume that all props are persistent; filter against the props listed in ClassMetadata
        foreach ($propValues as $prop => $value) {
            $classProperty = $classMetadata->properties[$prop];

            foreach ($classProperty->getFieldNames() as $fieldName) {
                $fieldNames[] = $fieldName;
            }

            foreach ($classProperty->propToFields($value) as $fieldValue) {
                $fieldValues[] = $fieldValue;
            }
        }

        $sql = $this->getInsertSQL($classMetadata->tableName, $fieldNames);
        $statement = $this->connection->prepare($sql);
        $statement->execute($fieldValues);

        // Set the identity property if the table is auto-increment
        if ($classMetadata->isAutoIncrement) {
            $lastInsertId = $this->connection->lastInsertId();

            $prop = $classMetadata->idProperties[0]; // can only be a single property mapping to a single field
            $value = $classMetadata->properties[$prop]->fieldsToProp([$lastInsertId]);

            $this->objectFactory->hydrate($entity, [$prop => $value]);
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

        $props = array_keys($classMetadata->properties);
        $propValues = $this->objectFactory->read($entity, $props);

        foreach ($classMetadata->idProperties as $idProperty) {
            if (! isset($propValues[$idProperty])) {
                // @todo custom exception
                throw new \RuntimeException('Cannot update() an entity with no identity.');
            }
        }

        $updateFieldNames = [];
        $whereFieldNames = [];
        $fieldValues = [];

        foreach ($classMetadata->nonIdProperties as $prop) {
            if (isset($propValues[$prop])) {
                $classProperty = $classMetadata->properties[$prop];

                foreach ($classProperty->getFieldNames() as $fieldName) {
                    $updateFieldNames[] = $fieldName;
                }

                foreach ($classProperty->propToFields($propValues[$prop]) as $fieldValue) {
                    $fieldValues[] = $fieldValue;
                }
            }
        }

        foreach ($classMetadata->idProperties as $prop) {
            $classProperty = $classMetadata->properties[$prop];

            foreach ($classProperty->getFieldNames() as $fieldName) {
                $whereFieldNames[] = $fieldName;
            }

            foreach ($classProperty->propToFields($propValues[$prop]) as $fieldValue) {
                $fieldValues[] = $fieldValue;
            }
        }

        $sql = $this->getUpdateSQL($classMetadata->tableName, $updateFieldNames, $whereFieldNames);
        $statement = $this->connection->prepare($sql);
        $statement->execute($fieldValues);
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
     * @param array  $id    The identity, as an associative array of property name to value.
     *
     * @return void
     */
    public function removeIdentity(string $class, array $id) : void
    {
        $classMetadata = $this->classMetadata[$class];

        $whereFields = [];
        $whereFieldValues = [];

        foreach ($id as $prop => $value) {
            $classProperty = $classMetadata->properties[$prop];

            foreach ($classProperty->getFieldNames() as $fieldName) {
                $whereFields[] = $fieldName;
            }

            foreach ($classProperty->propToFields($value) as $fieldValue) {
                $whereFieldValues[] = $fieldValue;
            }
        }

        $sql = $this->getDeleteSQL($classMetadata->tableName, $whereFields);
        $statement = $this->connection->prepare($sql);
        $statement->execute($whereFieldValues);
    }

    /**
     * @param string $class  The entity class name.
     * @param object $entity The entity.
     *
     * @return array The identity, as an associative array of property name to value.
     *
     * @throws \RuntimeException
     */
    private function getIdentity(string $class, object $entity) : array
    {
        $classMetadata = $this->classMetadata[$class];

        $identity = $this->objectFactory->read($entity, $classMetadata->idProperties);

        if (count($identity) !== count($classMetadata->idProperties)) {
            // @todo NoIdentityException
            throw new \RuntimeException('The entity has no identity.');
        }

        return $identity;
    }
}
