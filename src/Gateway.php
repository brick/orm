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
     * The identity map, to keep references to managed entities, if any.
     *
     * @var IdentityMap|null
     */
    private $identityMap;

    /**
     * Whether to use lazy-loading proxies to reference entities whose identity is known, but data is not yet known.
     *
     * If false, partial objects with no lazy-loading capability will be used instead.
     *
     * @var bool
     */
    private $useProxies;

    /**
     * @var ObjectFactory
     */
    private $objectFactory;

    /**
     * Gateway constructor.
     *
     * @param Connection       $connection
     * @param EntityMetadata[] $classMetadata
     * @param IdentityMap|null $identityMap
     * @param bool             $useProxies
     */
    public function __construct(Connection $connection, array $classMetadata, ?IdentityMap $identityMap = null, bool $useProxies = false)
    {
        $this->connection    = $connection;
        $this->classMetadata = $classMetadata;
        $this->identityMap   = $identityMap;
        $this->useProxies    = $useProxies;
        $this->objectFactory = new ObjectFactory();
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
     * An optional array of property names can be provided, to load a partial object.
     * By default, all properties will be loaded and set.
     *
     * @param string $class    The entity class name.
     * @param array  $id       The identity, as a map of property name to value.
     * @param int    $lockMode The lock mode.
     * @param string ...$props An optional array of property names to load.
     *
     * @return object|null The entity, or null if it doesn't exist.
     *
     * @throws \RuntimeException If a property name does not exist.
     */
    public function load(string $class, array $id, int $lockMode, string ...$props) : ?object
    {
        $query = new Query($class);

        if ($props) {
            $query->setProperties(...$props);
        }

        foreach ($id as $prop => $value) {
            $query->addPredicate($prop, '=', $value);
        }

        return $this->findOne($query, $lockMode);
    }

    /**
     * Loads an entity's properties.
     *
     * @param string   $class    The entity class name.
     * @param array    $id       The identity, as a map of property name to value.
     * @param string[] $props    The list of property names to load.
     * @param int      $lockMode The lock mode to use.
     *
     * @return array|null The properties, or null if the entity doesn't exist.
     *
     * @throws \RuntimeException If a property name does not exist.
     */
    public function loadProps(string $class, array $id, array $props, int $lockMode = LockMode::NONE) : ?array
    {
        $query = new Query($class);
        $query->setProperties(...$props);

        foreach ($id as $prop => $value) {
            $query->addPredicate($prop, '=', $value);
        }

        $result = $this->doFind($query, $lockMode);

        if (! $result) {
            return null;
        }

        return $result[0][1];
    }

    /**
     * Loads the properties of the given entity.
     *
     * The entity must have an identity.
     * By default, all properties are loaded. If a list of properties if given, only these properties will be loaded.
     *
     * @param object $entity   The entity to hydrate.
     * @param int    $lockMode The lock mode.
     * @param string ...$props An optional list of properties to hydrate.
     *
     * @return void
     *
     * @throws \RuntimeException If the entity has no identity, or is not found. @todo custom exceptions.
     */
    public function hydrate(object $entity, int $lockMode = LockMode::NONE, string ...$props) : void
    {
        $class = get_class($entity);
        $identity = $this->getIdentity($class, $entity);

        $values = $this->loadProps($class, $identity, $props, $lockMode);

        if ($values === null) {
            throw new \RuntimeException('Entity not found.');
        }

        $this->objectFactory->write($entity, $values);
    }

    /**
     * Finds entities using a query object.
     *
     * @todo custom exceptions
     *
     * @param Query $query        The query object.
     * @param int   $lockMode     The lock mode.
     * @param bool  $forceRefresh Whether to force refresh of entities when LockMode::NONE is provided.
     *                            By default, LockMode::NONE does not refresh entities, while other lock modes do.
     *
     * @return object[]
     */
    public function find(Query $query, int $lockMode = LockMode::NONE, bool $forceRefresh = false) : array
    {
        $entities = [];

        $refresh = $forceRefresh || ($lockMode !== LockMode::NONE);

        foreach ($this->doFind($query, $lockMode) as [$className, $propValues]) {
            $classMetadata = $this->classMetadata[$className];

            if ($this->identityMap !== null) {
                $identity = [];

                foreach ($classMetadata->idProperties as $idProperty) {
                    if (! isset($propValues[$idProperty])) {
                        throw new \Exception(
                            'Object\'s identity must be retrieved when running with an identity map. ' .
                            'Please add "' . $idProperty . '" to loaded properties of "' . $className . '".'
                        );
                    }

                    $identity[] = $propValues[$idProperty];
                }

                // Get the existing entity from the identity map, if any.
                $entity = $this->identityMap->get($classMetadata->rootClassName, $identity);

                if ($refresh) {
                    // If the entity already exists in the identity map, refresh it.

                    if ($entity === null) {
                        $entity = $this->objectFactory->instantiate($className, $classMetadata->properties);
                        $this->identityMap->set($classMetadata->rootClassName, $identity, $entity);
                    }

                    $this->objectFactory->write($entity, $propValues);
                } else {
                    // Use the entity from the identity map if it exists.

                    if ($entity === null) {
                        $entity = $this->objectFactory->instantiate($className, $classMetadata->properties);
                        $this->objectFactory->write($entity, $propValues);
                    }
                }
            } else {
                // No identity map, always create a new entity.
                $entity = $this->objectFactory->instantiate($className, $classMetadata->properties);
                $this->objectFactory->write($entity, $propValues);
            }

            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * Finds entities using a query object, and returns them as class names and properties.
     *
     * The result is a list, whose each element is itself a list containing exactly 2 elements:
     *
     * - the class name of the entity as a string;
     * - a map of property name to value as an array.
     *
     * @param Query $query    The query object.
     * @param int   $lockMode The lock mode.
     *
     * @return array
     */
    private function doFind(Query $query, int $lockMode = LockMode::NONE) : array
    {
        $className = $query->getClassName();

        if (! isset($this->classMetadata[$className])) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid entity.', $className));
        }

        $classMetadata = $this->classMetadata[$className];

        $props = $query->getProperties();

        $isFullObject = ($props === null);

        if ($props === null) {
            $props = $classMetadata->properties;
        } else {
            $props = array_values(array_unique($props));

            foreach ($props as $prop) {
                if (! isset($classMetadata->propertyMappings[$prop])) {
                    // @todo UnknownPropertyException
                    throw new \RuntimeException(sprintf('The %s::$%s property does not exist or is transient.', $className, $prop));
                }
            }
        }

        $tableAliasGenerator = new TableAliasGenerator();
        $mainTableAlias = $tableAliasGenerator->generate();

        $selectFields = [];

        if ($classMetadata->discriminatorColumn !== null) {
            $selectFields[] = $mainTableAlias . '.' . $classMetadata->discriminatorColumn; // @todo quote field name
        }

        foreach ($props as $prop) {
            $propertyMapping = $classMetadata->propertyMappings[$prop];

            // @todo quote field names
            $fieldNames = $propertyMapping->getFieldNames();

            foreach ($fieldNames as $key => $fieldName) {
                $fieldNames[$key] = $mainTableAlias . '.' . $fieldName;
            }

            $fieldToInputValuesSQL = $propertyMapping->getFieldToInputValuesSQL($fieldNames);

            foreach ($fieldToInputValuesSQL as $selectField) {
                $selectFields[] = $selectField;
            }
        }

        if ($isFullObject) {
            foreach ($classMetadata->childClasses as $childClass) {
                $childClassMetadata = $this->classMetadata[$childClass];

                foreach ($childClassMetadata->selfNonIdProperties as $prop) {
                    $propertyMapping = $childClassMetadata->propertyMappings[$prop];

                    // @todo quote field names
                    $fieldNames = $propertyMapping->getFieldNames();

                    foreach ($fieldNames as $key => $fieldName) {
                        $fieldNames[$key] = $mainTableAlias . '.' . $fieldName;
                    }

                    $fieldToInputValuesSQL = $propertyMapping->getFieldToInputValuesSQL($fieldNames);

                    foreach ($fieldToInputValuesSQL as $selectField) {
                        $selectFields[] = $selectField;
                    }
                }
            }
        }

        if (! $selectFields) {
            // No fields are selected, just perform a SELECT 1
            $selectFields[] = '1';
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
                            $whereConditions[] = $tableAlias . '.' . $fieldNames[$fieldNameIndex] . ' ' . $operator . ' ' . $expressionOrValue;
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

        $result = [];

        while (null !== $inputValues = $statement->fetch()) {
            $index = 0;
            $propValues = [];

            if ($classMetadata->discriminatorColumn !== null) {
                $discriminatorValue = $inputValues[$index++];
                $actualClass = $classMetadata->discriminatorMap[$discriminatorValue];

                if ($actualClass !== $className && ! is_subclass_of($actualClass, $className)) {
                    // @todo custom exception
                    throw new \Exception(sprintf('Expected instance of %s, got %s.', $className, $actualClass));
                }

                $className = $actualClass;
            }

            foreach ($props as $prop) {
                $propertyMapping = $classMetadata->propertyMappings[$prop];
                $valuesCount = $propertyMapping->getInputValuesCount();

                $propInputValues = array_slice($inputValues, $index, $valuesCount);
                $index += $valuesCount;

                $propValues[$prop] = $propertyMapping->convertInputValuesToProp($this, $propInputValues);
            }

            if ($isFullObject) {
                foreach ($classMetadata->childClasses as $childClass) {
                    $childClassMetadata = $this->classMetadata[$childClass];

                    foreach ($childClassMetadata->selfNonIdProperties as $prop) {
                        $propertyMapping = $childClassMetadata->propertyMappings[$prop];
                        $valuesCount = $propertyMapping->getInputValuesCount();

                        if ($childClass === $className || is_subclass_of($className, $childClass)) {
                            $propInputValues = array_slice($inputValues, $index, $valuesCount);
                            $propValues[$prop] = $propertyMapping->convertInputValuesToProp($this, $propInputValues);
                        }

                        $index += $valuesCount;
                    }
                }
            }

            $result[] = [$className, $propValues];
        }

        return $result;
    }

    /**
     * Adds joins for the given property, returns the alias of the table to read from, and the property mapping.
     *
     * @todo Quick & dirty. Refactor.
     */
    private function addJoins(EntityMetadata $classMetadata, SelectQueryBuilder $selectBuilder, TableAliasGenerator $tableAliasGenerator, string $mainTableAlias, array & $tableAliases, string $dottedProperty) : array
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
    public function getReference(string $class, array $id) : object
    {
        $classMetadata = $this->classMetadata[$class];

        if (array_keys($id) !== $classMetadata->idProperties) {
            // @todo a map in an incorrect order should be allowed: attempt to reorder keys.
            // @todo custom exception.
            throw new \Exception('Invalid identity.');
        }

        if ($this->identityMap !== null) {
            $entity = $this->identityMap->get($classMetadata->rootClassName, $id);

            if ($entity === null) {
                $entity = $this->instantiate($classMetadata, $class, $id);
                $this->identityMap->set($classMetadata->rootClassName, $id, $entity);
            } elseif (! $entity instanceof $class) {
                // Consistency check: if we request a subclass of rootClassName, and the object in the identity map
                // is not an instance of the subclass.
                throw new \Exception('Expected instance of "' . $class . '", got instance of "' . get_class($entity) . '".');
            }

            return $entity;
        }

        return $this->instantiate($classMetadata, $class, $id);
    }

    /**
     * @param EntityMetadata $classMetadata
     * @param string         $class
     * @param array          $id
     *
     * @return object
     */
    private function instantiate(EntityMetadata $classMetadata, string $class, array $id) : object
    {
        if ($this->useProxies) {
            // Returns a lazy-loading proxy, with the identity set and other properties lazy-loaded on first access.
            $proxyClass = $classMetadata->proxyClassName;

            return new $proxyClass($this, $id);
        }

        // Return a partial object, with only the identity set.

        $entity = $this->objectFactory->instantiate($class, $classMetadata->properties);
        $this->objectFactory->write($entity, $id);

        return $entity;
    }

    /**
     * Returns whether the given entity exists in the database.
     *
     * @param object $entity The entity to check.
     *
     * @return bool
     */
    public function exists(object $entity) : bool
    {
        $class = get_class($entity);

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
        return $this->loadProps($class, $id, [], LockMode::NONE) !== null;
    }

    /**
     * Saves the given entity to the database.
     *
     * This results in an immediate INSERT statement being executed against the database.
     * If an identity map is in use, the object is added to the identity map on successful completion of the INSERT.
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

        $identity = null;

        // Set the identity property if the table is auto-increment
        if ($classMetadata->isAutoIncrement) {
            $lastInsertId = $this->connection->lastInsertId();

            // This can only be a single property mapping to a single field, using a single scalar value.
            $prop = $classMetadata->idProperties[0];
            $value = $classMetadata->propertyMappings[$prop]->convertInputValuesToProp($this, [$lastInsertId]);

            $this->objectFactory->write($entity, [$prop => $value]);

            $identity = [$value];
        }

        if ($this->identityMap !== null) {
            if ($identity === null) {
                foreach ($classMetadata->idProperties as $idProperty) {
                    $identity[] = $propValues[$idProperty];
                }
            }

            $this->identityMap->set($classMetadata->rootClassName, $identity, $entity);
        }
    }

    /**
     * Updates the given entity in the database.
     *
     * This results in an immediate UPDATE statement being executed against the database.
     *
     * By default, all persistent properties are considered, unless a list of properties is given, in which case only
     * these properties will be considered. Non-initialized properties are skipped.
     *
     * @param object $entity   The entity to update.
     * @param string ...$props An optional list of properties to update.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function update(object $entity, string ...$props) : void
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

        $values = [];

        $props = $props ?: $classMetadata->nonIdProperties;

        foreach ($props as $prop) {
            if (array_key_exists($prop, $propValues)) {
                $values[$prop] = $propValues[$prop];
            }

            // @todo exception if an unknown property is passed as a parameter?
            //       this will be necessary if this method is part of the public API.
        }

        $id = [];

        foreach ($classMetadata->idProperties as $prop) {
            $id[$prop] = $propValues[$prop];
        }

        $this->doUpdate($class, $values, $id);
    }

    /**
     * Updates an entity in the database.
     *
     * This results in an immediate UPDATE statement being executed against the database.
     *
     * @param string $class
     * @param array  $values A map of updatable property name to value.
     * @param array  $id     A map of identity property name to value.
     *
     * @return void
     */
    public function doUpdate(string $class, array $values, array $id) : void
    {
        $classMetadata = $this->classMetadata[$class];

        $updates = [];
        $whereConditions = [];
        $outputValues = [];

        foreach ($values as $prop => $value) {
            $propertyMapping = $classMetadata->propertyMappings[$prop];
            $expressionsAndOutputValues = $propertyMapping->convertPropToFields($value);

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

        $sql = $this->getUpdateSQL($classMetadata->tableName, $updates, $whereConditions);
        $statement = $this->connection->prepare($sql);
        $statement->execute($outputValues);
    }

    /**
     * Removes the given entity from the database.
     *
     * This results in an immediate DELETE statement being executed against the database.
     *
     * @param object $entity The entity to remove.
     *
     * @return void
     */
    public function remove(object $entity) : void
    {
        $class = get_class($entity);

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
