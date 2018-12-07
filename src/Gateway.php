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
     * @param string[] $selectFields  The field names to select.
     * @param string[] $whereFields   The field names part of the WHERE clause.
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
     * @param ClassMetadata $classMetadata The class metadata.
     * @param array         $propValues    An associative array of property name to value.
     *
     * @return array A numeric array of database field values.
     */
    private function getFieldValues(ClassMetadata $classMetadata, array $propValues) : array
    {
        $fieldValues = [];

        foreach ($propValues as $prop => $value) {
            foreach ($classMetadata->properties[$prop]->propToFields($value) as $fieldValue) {
                $fieldValues[] = $fieldValue;
            }
        }

        return $fieldValues;
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

        foreach ($id as $prop => $value) {
            foreach ($classMetadata->properties[$prop]->getFieldNames() as $fieldName) {
                $whereFields[] = $fieldName;
            }
        }

        $sql = $this->getSelectSQL($classMetadata->tableName, $selectFields, $whereFields, $lockMode);
        $statement = $this->connection->prepare($sql);

        $values = $this->getFieldValues($classMetadata, $id);
        $statement->execute($values);

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
     */
    public function save(string $class, object $entity) : void
    {

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
     */
    public function update(string $class, object $entity) : void
    {

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

    }

    /**
     * @param string $class  The entity class name.
     * @param object $entity The entity.
     *
     * @return array The identity, as an associative array of property name to value.
     */
    private function getIdentity(string $class, object $entity) : array
    {
        $classMetadata = $this->classMetadata[$class];

        return $this->objectFactory->read($entity, $classMetadata->idProperties);
    }
}
