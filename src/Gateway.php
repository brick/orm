<?php

declare(strict_types=1);

namespace Brick\ORM;

use Brick\Db\Connection;
use Brick\ORM\PropertyMapping\BuiltinTypeMapping;
use Brick\ORM\PropertyMapping\EmbeddableMapping;
use Brick\ORM\PropertyMapping\EntityMapping;

/**
 * The underlying table data gateway for repositories.
 *
 * This is a low-level class that assumes some level of validation in call parameters, and can fail badly with obscure
 * error messages when called with improper parameters. This class is not part of the public API and may change at any
 * time. Use the generated repositories instead. You have been warned.
 *
 * Current limitations:
 * - No support for private properties (for simplicity, performuance, ease of requesting property names in load(), and lazy initialization proxies)
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
     * The entity metadata, indexed by class name.
     *
     * @var EntityMetadata[]
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
     * @param int           $lockMode The lock mode.
     * @param string[]|null $props    An optional array of property names to load.
     *
     * @return object|null The entity, or null if it doesn't exist.
     *
     * @throws \RuntimeException If a property name does not exist.
     */
    public function load(string $class, array $id, int $lockMode, ?array $props) : ?object
    {
        $propValues = $this->loadProps($class, $id, $lockMode, $props);

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
     * @param int           $lockMode The lock mode.
     * @param string[]|null $props    An optional array of property names to load. Defaults to non-id properties.
     *
     * @return array|null The properties, or null if the entity doesn't exist.
     *
     * @throws \RuntimeException If a property name does not exist.
     */
    public function loadProps(string $class, array $id, int $lockMode, ?array $props) : ?array
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

            $expressionsAndOutputValues = $propertyMapping->convertPropToFields($value);

            foreach ($propertyMapping->getFieldNames() as $fieldNameIndex => $fieldName) {
                // @todo keep an eye on https://wiki.php.net/rfc/spread_operator_for_array (maybe PHP 7.4?)
                // this would allow for foreach (... as [$expression, ...$values])
                foreach ($expressionsAndOutputValues[$fieldNameIndex] as $index => $expressionOrValue) {
                    if ($index === 0) {
                        $whereConditions[] = $fieldName . ' = ' . $expressionOrValue;
                    } else {
                        $outputValues[] = $expressionOrValue;
                    }
                }
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
     * @todo custom exceptions
     *
     * @param Query $query
     * @param int   $lockMode
     *
     * @return object[]
     */
    public function find(Query $query, int $lockMode = LockMode::NONE) : array
    {
        $className = $query->getClassName();

        if (! isset($this->classMetadata[$className])) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid entity.', $className));
        }

        $classMetadata = $this->classMetadata[$className];

        $props = $query->getProperties();

        if ($props === null) {
            $props = $classMetadata->properties;
        } else {
            $props = array_values(array_unique($props));

            foreach ($props as $prop) {
                if (! isset($classMetadata->propertyMappings[$prop])) {
                    // @todo UnknownPropertyException
                    throw new \RuntimeException(sprintf('The %s::$%s property does not exist.', $className, $prop));
                }
            }
        }

        $tableAliasGenerator = new TableAliasGenerator();
        $mainTableAlias = $tableAliasGenerator->generate();

        $selectFields = [];

        foreach ($props as $prop) {
            $propertyMapping = $classMetadata->propertyMappings[$prop];

            // @todo quote field names
            $fieldNames = $propertyMapping->getFieldNames();
            $fieldToInputValuesSQL = $propertyMapping->getFieldToInputValuesSQL($fieldNames);

            foreach ($fieldToInputValuesSQL as $selectField) {
                $selectFields[] = $mainTableAlias . '.' . $selectField;
            }
        }

        $outputValues = [];
        $tableAliases = []; // Table aliases, indexed by (dotted) property name.

        $selectBuilder = new SelectQueryBuilder($selectFields, $classMetadata->tableName, $mainTableAlias);
        $selectBuilder->setLockMode($lockMode);

        foreach ($query->getPredicates() as $predicate) {
            /** @var string $tableAlias */
            /** @var PropertyMapping $propertyMapping */
            [$tableAlias, $propertyMapping] = $this->addJoins($classMetadata, $selectBuilder, $tableAliasGenerator, $mainTableAlias, $tableAliases, $predicate->getProperty());

            $operator = $predicate->getOperator();

            if ($operator !== '=' && $operator !== '!=' && ! $propertyMapping instanceof BuiltinTypeMapping) {
                // @todo custom exception
                throw new \Exception(sprintf('Operator %s can only be used on builtin types.', $operator));
            }

            $whereConditions = [];

            // @todo check that $value is of the correct type
            $value = $predicate->getValue();

            $fieldNames = $propertyMapping->getFieldNames();

            if ($value === null) {
                // property = null implies IS NULL on every single underlying database field
                // property != null currently implies IS NOT NULL on every single underlying database field
                // @todo property != null should imply IS NOT NULL on ANY OF the non-nullable fields
                foreach ($fieldNames as $fieldName) { // @todo quote field name
                    if ($operator !== '=' && $operator != '!=') {
                        // @todo custom exception
                        throw new \Exception(sprintf('Operator %s cannot be used on null values.', $operator));
                    }

                    $whereConditions[] = $tableAlias . '.' . $fieldName . ' ' . ($operator === '=' ? 'IS NULL' : 'IS NOT NULL');
                }
            } else {
                $expressionsAndOutputValues = $propertyMapping->convertPropToFields($value);

                foreach ($fieldNames as $fieldNameIndex => $fieldName) {
                    foreach ($expressionsAndOutputValues[$fieldNameIndex] as $index => $expressionOrValue) {
                        if ($index === 0) {
                            $whereConditions[] = $tableAlias . '.' . $fieldNames[$index] . ' ' . $operator . ' ' . $expressionOrValue;
                        } else {
                            $outputValues[] = $expressionOrValue;
                        }
                    }
                }
            }

            // a = x AND b = y, but (A != x OR b != y); other operators not allowed on multiple fields
            $selectBuilder->addWhereConditions($whereConditions, $operator === '!=' ? 'OR' : 'AND');
        }

        foreach ($query->getOrderBy() as $orderBy) {
            // @todo There is currently an unnecessary JOIN when ordering by an identity field of a related entity;
            // example: ->addOrderBy('relatedEntity.id'); this could be avoided by reading the value from the base entity instead.

            /** @var string $tableAlias */
            /** @var PropertyMapping $propertyMapping */
            [$tableAlias, $propertyMapping] = $this->addJoins($classMetadata, $selectBuilder, $tableAliasGenerator, $mainTableAlias, $tableAliases, $orderBy->getProperty());

            foreach ($propertyMapping->getFieldNames() as $fieldName) {
                $selectBuilder->addOrderBy($tableAlias . '.' . $fieldName, $orderBy->getDirection());
            }
        }

        if (null !== $limit = $query->getLimit()) {
            $selectBuilder->setLimit($limit, $query->getOffset());
        }

        $sql = $selectBuilder->build();
        $statement = $this->connection->prepare($sql);
        $statement->execute($outputValues);

        $entities = [];

        while (null !== $inputValues = $statement->fetch()) {
            $index = 0;
            $propValues = [];

            foreach ($props as $prop) {
                $propertyMapping = $classMetadata->propertyMappings[$prop];
                $valuesCount = $propertyMapping->getInputValuesCount();

                $propInputValues = array_slice($inputValues, $index, $valuesCount);
                $index += $valuesCount;

                $propValues[$prop] = $propertyMapping->convertInputValuesToProp($this, $propInputValues);
            }

            $entity = $this->objectFactory->instantiate($className, $classMetadata->properties);
            $this->objectFactory->write($entity, $propValues);

            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * Adds joins for the given property, returns the alias of the table to read from, and the property mapping.
     *
     * @todo Quick & dirty. Refactor.
     */
    private function addJoins(ClassMetadata $classMetadata, SelectQueryBuilder $selectBuilder, TableAliasGenerator $tableAliasGenerator, string $mainTableAlias, array & $tableAliases, string $dottedProperty) : array
    {
        $properties = explode('.', $dottedProperty);
        $count = count($properties);

        $currentClassMetadata = $classMetadata;
        $isEntityOrEmbeddable = true;

        foreach ($properties as $index => $property) {
            if (! $isEntityOrEmbeddable) {
                // Requesting a child property of a non-entity or embeddable property
                throw new \InvalidArgumentException(sprintf('%s is not a valid property for %s.', $dottedProperty, $classMetadata->className));
            }

            if (! isset($currentClassMetadata->propertyMappings[$property])) {
                throw new \InvalidArgumentException(sprintf('%s has no property named $%s.', $currentClassMetadata->className, $property));
            }

            $propertyMapping = $currentClassMetadata->propertyMappings[$property];

            if ($propertyMapping instanceof EmbeddableMapping) {
                continue;
            }

            if (! $propertyMapping instanceof EntityMapping) {
                $isEntityOrEmbeddable = false;
                continue;
            }

            // @todo target entity should be part of ClassMetadata itself?
            $currentClassMetadata = $propertyMapping->classMetadata;

            $joinProp = implode('.', array_slice($properties, 0, $index + 1));

            // Note: no need to JOIN if performing comparisons against the entity's identity only.
            // Only JOIN if the entity is not the last element of the dotted property.
            if (($index !== $count - 1) && ! isset($tableAliases[$joinProp]) && $propertyMapping instanceof EntityMapping) {
                $tableAlias = $tableAliasGenerator->generate();
                $tableAliases[$joinProp] = $tableAlias;

                if ($index === 0) {
                    $sourceTableAlias = $mainTableAlias;
                } else {
                    $previousJoinProp = implode('.', array_slice($properties, 0, $index));
                    $sourceTableAlias = $tableAliases[$previousJoinProp];
                }

                $joinConditions = [];

                foreach ($currentClassMetadata->idProperties as $prop) {
                    foreach ($currentClassMetadata->propertyMappings[$prop]->getFieldNames() as $name) {
                        // @todo quote field names
                        $joinConditions[] = $sourceTableAlias . '.' . $propertyMapping->fieldNamePrefix . $name . ' = ' . $tableAlias . '.' . $name;
                    }
                }

                $selectBuilder->addJoin(
                    $propertyMapping->isNullable() ? 'LEFT' : 'INNER',
                    $currentClassMetadata->tableName,
                    $tableAlias,
                    $joinConditions
                );
            }
        }

        // Loop from the next-to-last dotted property, up to the root property, until we find a table alias:
        // we might have embeddabled in between, that must use the parent table.
        for ($i = 1; ; $i++) {
            $previousJoinProp = implode('.', array_slice($properties, 0, count($properties) - $i));
            if ($previousJoinProp === '') {
                $tableAlias = $mainTableAlias;
                break;
            }

            if (isset($tableAliases[$previousJoinProp])) {
                $tableAlias = $tableAliases[$previousJoinProp];
                break;
            }
        }

        return [$tableAlias, $propertyMapping];
    }

    /**
     * @param Query $query
     * @param int   $lockMode
     *
     * @return object|null
     *
     * @throws \Exception @todo NonUniqueResultException
     */
    public function findOne(Query $query, int $lockMode = LockMode::NONE) : ?object
    {
        $entities = $this->find($query, $lockMode);
        $count = count($entities);

        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return $entities[0];
        }

        // @todo NonUniqueResultException
        throw new \Exception(sprintf('The query returned %u results, expected at most 1.', $count));
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
        return $this->load($class, $id, LockMode::NONE, []) !== null;
    }

    /**
     * Saves the given entity to the database.
     *
     * This results in an immediate INSERT statement being executed against the database.
     *
     * @param object $entity The entity to save.
     *
     * @return void
     *
     * @throws \RuntimeException
     * @throws \Brick\Db\DbException If a database error occurs.
     */
    public function save(object $entity) : void
    {
        $class = get_class($entity);

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

        if ($classMetadata->discriminatorColumn !== null) {
            $fieldNames[] = $classMetadata->discriminatorColumn; // @todo quote field name
            $fieldValues[] = '?';
            $outputValues[] = $classMetadata->discriminatorValue;
        }

        foreach ($propValues as $prop => $value) {
            if (! isset($classMetadata->propertyMappings[$prop])) {
                // Non-persistent property
                continue;
            }

            $propertyMapping = $classMetadata->propertyMappings[$prop];

            // @todo workaround to avoid sending NULL values for non-initialized properties,
            // such as an auto-increment id in a new object. Remove for PHP 7.4 when these fields will be
            // uninitialized instead of null, and will be naturally skipped.
            if ($value === null && ! $propertyMapping->isNullable()) {
                continue;
            }

            $expressionsAndOutputValues = $propertyMapping->convertPropToFields($value);

            foreach ($propertyMapping->getFieldNames() as $fieldNameIndex => $fieldName) {
                foreach ($expressionsAndOutputValues[$fieldNameIndex] as $index => $expressionOrValue) {
                    if ($index === 0) {
                        $fieldNames[] = $fieldName; // @todo quote field name
                        $fieldValues[] = $expressionOrValue;
                    } else {
                        $outputValues[] = $expressionOrValue;
                    }
                }
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
     * @param object $entity The entity to update.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function update(object $entity) : void
    {
        $class = get_class($entity);

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
            if (array_key_exists($prop, $propValues)) {
                $propertyMapping = $classMetadata->propertyMappings[$prop];
                $expressionsAndOutputValues = $propertyMapping->convertPropToFields($propValues[$prop]);

                foreach ($propertyMapping->getFieldNames() as $fieldNameIndex => $fieldName) { // @todo quote field name
                    foreach ($expressionsAndOutputValues[$fieldNameIndex] as $index => $expressionOrValue) {
                        if ($index === 0) {
                            $updates[] = $fieldName . ' = ' . $expressionOrValue;
                        } else {
                            $outputValues[] = $expressionOrValue;
                        }
                    }
                }
            }
        }

        foreach ($classMetadata->idProperties as $prop) {
            $propertyMapping = $classMetadata->propertyMappings[$prop];
            $expressionsAndOutputValues = $propertyMapping->convertPropToFields($propValues[$prop]);

            foreach ($propertyMapping->getFieldNames() as $fieldNameIndex => $fieldName) { // @todo quote field name
                foreach ($expressionsAndOutputValues[$fieldNameIndex] as $index => $expressionOrValue) {
                    if ($index === 0) {
                        $whereConditions[] = $fieldName . ' = ' . $expressionOrValue;
                    } else {
                        $outputValues[] = $expressionOrValue;
                    }
                }
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
            $expressionsAndOutputValues = $propertyMapping->convertPropToFields($value);

            foreach ($propertyMapping->getFieldNames() as $fieldNameIndex => $fieldName) { // @todo quote field name
                foreach ($expressionsAndOutputValues[$fieldNameIndex] as $index => $expressionOrValue) {
                    if ($index === 0) {
                        $whereConditions[] = $fieldName . ' = ' . $expressionOrValue;
                    } else {
                        $outputValues[] = $expressionOrValue;
                    }
                }
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
