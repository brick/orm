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
 * - No support for private properties (for simplicity, performance, ease of requesting property names in load(), and lazy initialization proxies)
 * - No support for update() on mutated identities: explicitly remove() the previous identity then add() the new one
 *
 * @internal
 */
class Gateway
{
    private Connection $connection;

    /**
     * The entity metadata, indexed by class name.
     *
     * @var EntityMetadata[]
     */
    private array $classMetadata;

    /**
     * The identity map, to keep references to managed entities, if any.
     */
    private IdentityMap|null $identityMap;

    /**
     * Whether to use lazy-loading proxies to reference entities whose identity is known, but data is not yet known.
     *
     * If false, partial objects with no lazy-loading capability will be used instead.
     */
    private bool $useProxies;

    private ObjectFactory $objectFactory;

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
     * Performs a native query and instantiates DTOs.
     *
     * The DTO class must have only public, non-static properties, and no constructor.
     * The selected values must match the object properties.
     * Properties with no matching value will be left uninitialized.
     *
     * DTOs may contain other DTOs; to select values for nested DTOs, use the 'property__nestedProperty' syntax.
     * Properties pointing to a nested DTO will be initialized only if at least one nested property value is selected.
     *
     * @template T
     *
     * @psalm-param class-string<T> $className
     *
     * @psalm-return T[]
     *
     * @param string $className  The name of the class to instantiate.
     * @param string $query      The SQL query.
     * @param array  $parameters The bound parameters.
     *
     * @return object[] The instances of the class.
     *
     * @throws \Brick\Db\DbException
     */
    public function nativeQuery(string $className, string $query, array $parameters = []) : array
    {
        $statement = $this->connection->query($query, $parameters);
        $rows = $statement->fetchAllAssociative();

        $result = [];

        foreach ($rows as $row) {
            $values = $this->nestValues($row);
            $result[] = $this->objectFactory->instantiateDTO($className, $values);
        }

        return $result;
    }

    /**
     * Transforms a flat associative array into a nested array.
     *
     * Example: ['foo' => 'FOO', 'bar__baz' => 'BAZ'] would turn into ['foo' => 'FOO', 'bar' => ['baz' => 'BAZ']].
     *
     * @psalm-suppress EmptyArrayAccess
     * @psalm-suppress PossiblyNullArrayAccess
     * @psalm-suppress TypeDoesNotContainType
     *
     * @psalm-param array<string, mixed> $values
     *
     * @psalm-return array<string, mixed>
     */
    private function nestValues(array $values) : array
    {
        $result = [];

        foreach ($values as $name => $value) {
            $names = explode('__', $name);

            $ref = & $result;

            foreach ($names as $name) {
                if ($ref !== null && ! is_array($ref)) {
                    throw new \InvalidArgumentException('Invalid mix of scalar and non-scalar values.');
                }

                $ref = & $ref[$name];
            }

            $ref = $value;
        }

        return $result;
    }

    /**
     * Builds an INSERT query.
     *
     * The arrays of fields and values must have the same number of elements.
     *
     * @param string   $table       The table name.
     * @param string[] $fields      The list of field names.
     * @param string[] $expressions The list of SQL expressions.
     *
     * @return string
     */
    private function getInsertSQL(string $table, array $fields, array $expressions) : string
    {
        $fields = implode(', ', $fields);
        $expressions = implode(', ', $expressions);

        return sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, $fields, $expressions);
    }

    /**
     * @param string   $table           The table name.
     * @param string[] $updates         The list of 'key = value' pairs to update.
     * @param string[] $whereConditions The list of 'key = value' WHERE conditions.
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
     * @param string   $table           The table name.
     * @param string[] $whereConditions The list of 'key = value' WHERE conditions.
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
     * @psalm-param class-string $class
     * @psalm-param array<string, mixed> $id
     *
     * @param string $class    The entity class name.
     * @param array  $id       The identity, as a map of property name to value.
     * @param int    $options  A bitmask of options to use.
     * @param string ...$props An optional array of property names to load.
     *
     * @return object|null The entity, or null if it doesn't exist.
     *
     * @throws \RuntimeException If a property name does not exist.
     */
    public function load(string $class, array $id, int $options = 0, string ...$props) : object|null
    {
        $query = new Query($class);

        if ($props) {
            $query->setProperties(...$props);
        }

        foreach ($id as $prop => $value) {
            $query->addPredicate($prop, '=', $value);
        }

        return $this->findOne($query, $options);
    }

    /**
     * Loads an entity's properties.
     *
     * @psalm-param class-string $class
     * @psalm-param array<string, mixed> $id
     * @psalm-param list<string> $props
     *
     * @psalm-return array<string, mixed>
     *
     * @param string   $class    The entity class name.
     * @param array    $id       The identity, as a map of property name to value.
     * @param string[] $props    The list of property names to load.
     * @param int      $options  A bitmask of options to use.
     *
     * @return array|null The properties, or null if the entity doesn't exist.
     *
     * @throws \RuntimeException                     If a property name does not exist.
     * @throws Exception\UnknownEntityClassException If the class name is not a known entity class.
     * @throws Exception\UnknownPropertyException    If an unknown property is given.
     */
    public function loadProps(string $class, array $id, array $props, int $options = 0) : array|null
    {
        $query = new Query($class);
        $query->setProperties(...$props);

        foreach ($id as $prop => $value) {
            $query->addPredicate($prop, '=', $value);
        }

        $result = $this->doFind($query, $options);

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
     * @param int    $options  A bitmask of options to use.
     * @param string ...$props An optional list of properties to hydrate.
     *
     * @return void
     *
     * @throws Exception\UnknownEntityClassException If the object is not a known entity.
     * @throws Exception\UnknownPropertyException    If an unknown property is given.
     * @throws Exception\NoIdentityException         If the entity has no identity.
     * @throws Exception\EntityNotFoundException     If the entity is not found in the database.
     */
    public function hydrate(object $entity, int $options = 0, string ...$props) : void
    {
        $class = $this->getEntityClass($entity);
        $identity = $this->getIdentity($class, $entity);

        $values = $this->loadProps($class, $identity, $props, $options);

        if ($values === null) {
            $scalarIdentity = $this->getScalarIdentity($this->classMetadata[$class], $identity);

            throw Exception\EntityNotFoundException::entityNotFound($class, $scalarIdentity);
        }

        $this->objectFactory->write($entity, $values);
    }

    /**
     * Finds entities using a query object.
     *
     * @psalm-suppress MixedOperand See: https://github.com/vimeo/psalm/issues/4739
     *
     * @param Query $query   The query object.
     * @param int   $options A bitmask of options to use.
     *
     * @return object[]
     *
     * @throws Exception\UnknownEntityClassException If the query's class name is not a known entity class.
     * @throws Exception\UnknownPropertyException    If the query targets an unknown property.
     */
    public function find(Query $query, int $options = 0) : array
    {
        $entities = [];

        $refresh = ($options & Options::REFRESH || $options & Options::LOCK_READ || $options & Options::LOCK_WRITE);

        foreach ($this->doFind($query, $options) as [$className, $propValues]) {
            $classMetadata = $this->classMetadata[$className];

            if ($this->identityMap !== null) {
                $identity = [];

                foreach ($classMetadata->idProperties as $idProperty) {
                    if (! isset($propValues[$idProperty])) {
                        // @todo custom exception
                        throw new \Exception(
                            'Object\'s identity must be retrieved when running with an identity map. ' .
                            'Please add "' . $idProperty . '" to loaded properties of "' . $className . '".'
                        );
                    }

                    $identity[$idProperty] = $propValues[$idProperty];
                }

                $identity = $this->getScalarIdentity($classMetadata, $identity);

                // Get the existing entity from the identity map, if any.
                $entity = $this->identityMap->get($classMetadata->rootClassName, $identity);

                if ($entity === null) {
                    // Entity not found in the identity map, create and add it.
                    $entity = $this->objectFactory->instantiate($classMetadata, $propValues);
                    $this->identityMap->set($classMetadata->rootClassName, $identity, $entity);
                } elseif ($refresh) {
                    // Entity found in the identity map, but refresh is requested.
                    $this->objectFactory->write($entity, $propValues);
                }
            } else {
                // No identity map, always create a new entity.
                $entity = $this->objectFactory->instantiate($classMetadata, $propValues);
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
     * @psalm-return list<array{class-string, array<string, mixed>}>
     *
     * @param Query $query   The query object.
     * @param int   $options A bitmask of options to use.
     *
     * @throws Exception\UnknownEntityClassException If the query's class name is not a known entity class.
     * @throws Exception\UnknownPropertyException    If the query targets an unknown property.
     */
    private function doFind(Query $query, int $options = 0) : array
    {
        $className = $query->getClassName();

        if (! isset($this->classMetadata[$className])) {
            throw Exception\UnknownEntityClassException::unknownEntityClass($className);
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
                    throw Exception\UnknownPropertyException::unknownProperty($className, $prop);
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

            // https://github.com/vimeo/psalm/issues/4741
            /** @var list<string> $fieldNames */
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

                    // https://github.com/vimeo/psalm/issues/4741
                    /** @var list<string> $fieldNames */
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
        $selectBuilder->setOptions($options);

        foreach ($query->getPredicates() as $predicate) {
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
                            /** @var string $expressionOrValue */
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

            [$tableAlias, $propertyMapping] = $this->addJoins($classMetadata, $selectBuilder, $tableAliasGenerator, $mainTableAlias, $tableAliases, $orderBy->getProperty());

            foreach ($propertyMapping->getFieldNames() as $fieldName) {
                $selectBuilder->addOrderBy($tableAlias . '.' . $fieldName, $orderBy->getDirection());
            }
        }

        if (null !== $limit = $query->getLimit()) {
            $offset = $query->getOffset();
            assert($offset !== null);

            $selectBuilder->setLimit($limit, $offset);
        }

        $sql = $selectBuilder->build();
        $statement = $this->connection->prepare($sql);
        $statement->execute($outputValues);

        $result = [];

        foreach ($statement->iterateNumeric() as $inputValues) {
            $index = 0;
            $propValues = [];

            if ($classMetadata->discriminatorColumn !== null) {
                /** @var int|string $discriminatorValue */
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
     *
     * @psalm-param array<string, string> $tableAliases
     *
     * @psalm-return array{string, PropertyMapping}
     *
     * @psalm-suppress LessSpecificReturnStatement
     * @psalm-suppress MoreSpecificReturnType
     *
     * @throws Exception\UnknownPropertyException
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
                throw Exception\UnknownPropertyException::invalidDottedProperty($classMetadata->className, $dottedProperty);
            }

            if (! isset($currentClassMetadata->propertyMappings[$property])) {
                throw Exception\UnknownPropertyException::unknownProperty($currentClassMetadata->className, $property);
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
            if (($index !== $count - 1) && ! isset($tableAliases[$joinProp])) {
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

        /** @psalm-suppress PossiblyUndefinedVariable */
        return [$tableAlias, $propertyMapping];
    }

    /**
     * Finds a single entity using a query object.
     *
     * @param Query $query   The query object.
     * @param int   $options A bitmask of options to use.
     *
     * @return object|null The entity, or NULL if not found.
     *
     * @throws Exception\UnknownEntityClassException If the query's class name is not a known entity class.
     * @throws Exception\UnknownPropertyException    If the query targets an unknown property.
     * @throws Exception\NonUniqueResultException    If the query returns more than one result.
     */
    public function findOne(Query $query, int $options = 0) : object|null
    {
        $entities = $this->find($query, $options);
        $count = count($entities);

        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return $entities[0];
        }

        throw Exception\NonUniqueResultException::nonUniqueResult($count);
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
     * @psalm-param class-string $class
     * @psalm-param array<string, mixed> $id
     *
     * @param string $class The entity class name.
     * @param array  $id    The identity, as a map of property name to value.
     *
     * @return object
     *
     * @throws Exception\NoIdentityException If an entity with no identity is part of the given identity.
     */
    public function getReference(string $class, array $id) : object
    {
        $classMetadata = $this->classMetadata[$class];

        $scalarId = $this->getScalarIdentity($classMetadata, $id);

        if ($this->identityMap !== null) {
            $entity = $this->identityMap->get($classMetadata->rootClassName, $scalarId);

            if ($entity === null) {
                $entity = $this->instantiate($classMetadata, $id, $scalarId);
                $this->identityMap->set($classMetadata->rootClassName, $scalarId, $entity);
            } elseif (! $entity instanceof $class) {
                // Consistency check: if we request a subclass of rootClassName, and the object in the identity map
                // is not an instance of the subclass.
                throw new \Exception('Expected instance of "' . $class . '", got instance of "' . get_class($entity) . '".');
            }

            return $entity;
        }

        return $this->instantiate($classMetadata, $id, $scalarId);
    }

    /**
     * @psalm-param array<string, mixed> $id
     * @psalm-param list<int|string> $scalarId
     *
     * @param EntityMetadata $classMetadata The entity metadata.
     * @param array          $id            The identity, as a map of property name to value.
     * @param array          $scalarId      The identity, as a list of scalar values.
     *
     * @return object
     */
    private function instantiate(EntityMetadata $classMetadata, array $id, array $scalarId) : object
    {
        if ($this->useProxies) {
            // Return a lazy-loading proxy, with the identity set and other properties lazy-loaded on first access.
            $proxyClass = $classMetadata->proxyClassName;

            assert($proxyClass !== null);

            return new $proxyClass($this, $id, $scalarId);
        }

        // Return a partial object, with only the identity set.

        return $this->objectFactory->instantiate($classMetadata, $id);
    }

    /**
     * Returns whether the given entity exists in the database.
     *
     * @param object $entity The entity to check.
     *
     * @return bool
     *
     * @throws Exception\UnknownEntityClassException If the object is not a known entity.
     * @throws Exception\NoIdentityException         If the entity has no identity.
     */
    public function exists(object $entity) : bool
    {
        $class = $this->getEntityClass($entity);

        return $this->existsIdentity($class, $this->getIdentity($class, $entity));
    }

    /**
     * Returns whether an entity with the given identity exists in the database.
     *
     * @todo faster implementation
     *
     * @psalm-param class-string $class
     * @psalm-param array<string, mixed> $id
     *
     * @param string $class The entity class name.
     * @param array  $id    The identity, as a map of property name to value.
     *
     * @return bool
     */
    public function existsIdentity(string $class, array $id) : bool
    {
        return $this->loadProps($class, $id, []) !== null;
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
     * @throws Exception\UnknownEntityClassException If the object is not a known entity.
     * @throws Exception\NoIdentityException         If saving an entity with a non-autoincrement identity which is not set.
     * @throws \Brick\Db\DbException                 If a database error occurs.
     */
    public function add(object $entity) : void
    {
        $class = $this->getEntityClass($entity);

        $classMetadata = $this->classMetadata[$class];

        $propValues = $this->objectFactory->read($entity);

        if ($classMetadata->isAutoIncrement) {
            foreach ($classMetadata->idProperties as $idProperty) {
                if (isset($propValues[$idProperty])) {
                    // @todo custom exception
                    throw new \RuntimeException('Cannot add() an entity with an autoincrement identity already set. Use update() instead.');
                }
            }
        } else {
            foreach ($classMetadata->idProperties as $idProperty) {
                if (! isset($propValues[$idProperty])) {
                    throw new Exception\NoIdentityException('Cannot add() an entity with a non-autoincrement identity not set.');
                }
            }
        }

        $fieldNames = [];
        $sqlExpressions = [];
        $outputValues = [];

        if ($classMetadata->discriminatorColumn !== null) {
            $fieldNames[] = $classMetadata->discriminatorColumn; // @todo quote field name
            $sqlExpressions[] = '?';
            $outputValues[] = $classMetadata->discriminatorValue;
        }

        foreach ($classMetadata->propertyMappings as $prop => $propertyMapping) {
            if (! array_key_exists($prop, $propValues)) {
                if (in_array($prop, $classMetadata->idProperties)) {
                    // auto-increment id is allowed to be uninitialized
                    continue;
                }

                $message = sprintf('Entity of class %s cannot be add()ed because property $%s is not set.', $classMetadata->className, $prop);

                if ($propertyMapping->isNullable()) {
                    $message .= ' Did you forget to initialize it to null?';
                }

                throw new \RuntimeException($message);
            }

            $value = $propValues[$prop];
            $expressionsAndOutputValues = $propertyMapping->convertPropToFields($value);

            foreach ($propertyMapping->getFieldNames() as $fieldNameIndex => $fieldName) {
                foreach ($expressionsAndOutputValues[$fieldNameIndex] as $index => $expressionOrValue) {
                    if ($index === 0) {
                        $fieldNames[] = $fieldName; // @todo quote field name

                        /** @var string $expressionOrValue */
                        $sqlExpressions[] = $expressionOrValue;
                    } else {
                        $outputValues[] = $expressionOrValue;
                    }
                }
            }
        }

        $sql = $this->getInsertSQL($classMetadata->tableName, $fieldNames, $sqlExpressions);
        $statement = $this->connection->prepare($sql);
        $statement->execute($outputValues);

        $identity = null;

        // Set the identity property if the table is auto-increment
        if ($classMetadata->isAutoIncrement) {
            $lastInsertId = $this->connection->lastInsertId();

            // This can only be a single property mapping to a single field, using a single scalar (int|string) value.
            $prop = $classMetadata->idProperties[0];
            $value = $classMetadata->propertyMappings[$prop]->convertInputValuesToProp($this, [$lastInsertId]);

            $this->objectFactory->write($entity, [$prop => $value]);

            /** @var int|string $value */
            $identity = [$value];
        }

        if ($this->identityMap !== null) {
            if ($identity === null) {
                $identity = [];

                foreach ($classMetadata->idProperties as $idProperty) {
                    $identity[$idProperty] = $propValues[$idProperty];
                }

                $identity = $this->getScalarIdentity($classMetadata, $identity);
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
     * @throws Exception\UnknownEntityClassException If the object is not a known entity.
     * @throws Exception\UnknownPropertyException    If an unknown property is given.
     * @throws Exception\NoIdentityException         If the entity has no identity.
     */
    public function update(object $entity, string ...$props) : void
    {
        $class = $this->getEntityClass($entity);

        $classMetadata = $this->classMetadata[$class];

        $propValues = $this->objectFactory->read($entity);

        foreach ($classMetadata->idProperties as $idProperty) {
            if (! isset($propValues[$idProperty])) {
                throw new Exception\NoIdentityException('Cannot update() an entity with no identity.');
            }
        }

        $values = [];

        $props = $props ?: $classMetadata->nonIdProperties;

        foreach ($props as $prop) {
            if (array_key_exists($prop, $propValues)) {
                $values[$prop] = $propValues[$prop];
            }
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
     * @psalm-param class-string $class
     * @psalm-param array<string, mixed> $values
     * @psalm-param array<string, mixed> $id
     *
     * @param string $class
     * @param array  $values A map of updatable property name to value.
     * @param array  $id     A map of identity property name to value.
     *
     * @return void
     *
     * @throws Exception\UnknownPropertyException If an unknown property is given.
     */
    public function doUpdate(string $class, array $values, array $id) : void
    {
        $classMetadata = $this->classMetadata[$class];

        $updates = [];
        $whereConditions = [];
        $outputValues = [];

        foreach ($values as $prop => $value) {
            if (! isset($classMetadata->propertyMappings[$prop])) {
                throw Exception\UnknownPropertyException::unknownProperty($class, $prop);
            }

            $propertyMapping = $classMetadata->propertyMappings[$prop];
            $expressionsAndOutputValues = $propertyMapping->convertPropToFields($value);

            foreach ($propertyMapping->getFieldNames() as $fieldNameIndex => $fieldName) { // @todo quote field name
                foreach ($expressionsAndOutputValues[$fieldNameIndex] as $index => $expressionOrValue) {
                    if ($index === 0) {
                        /** @var string $expressionOrValue */
                        $updates[] = $fieldName . ' = ' . $expressionOrValue;
                    } else {
                        $outputValues[] = $expressionOrValue;
                    }
                }
            }
        }

        foreach ($id as $prop => $value) {
            // @todo check identity if this method is going to stay as part of the public API
            $propertyMapping = $classMetadata->propertyMappings[$prop];
            $expressionsAndOutputValues = $propertyMapping->convertPropToFields($value);

            foreach ($propertyMapping->getFieldNames() as $fieldNameIndex => $fieldName) { // @todo quote field name
                foreach ($expressionsAndOutputValues[$fieldNameIndex] as $index => $expressionOrValue) {
                    if ($index === 0) {
                        /** @var string $expressionOrValue */
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
     *
     * @throws Exception\UnknownEntityClassException If the object is not a known entity.
     */
    public function remove(object $entity) : void
    {
        $class = $this->getEntityClass($entity);

        $this->removeIdentity($class, $this->getIdentity($class, $entity));
    }

    /**
     * Removes the User with the given identity from the database.
     *
     * This results in an immediate DELETE statement being executed against the database.
     *
     * @psalm-param class-string $class
     * @psalm-param array<string, mixed> $id
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
                        /** @var string $expressionOrValue */
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
     * @psalm-param class-string $class
     *
     * @psalm-return array<string, mixed>
     *
     * @param string $class  The entity class name. Must be validated.
     * @param object $entity The entity.
     *
     * @return array The identity, as a map of property name to value.
     *
     * @throws Exception\NoIdentityException If the entity has no identity.
     */
    private function getIdentity(string $class, object $entity) : array
    {
        $classMetadata = $this->classMetadata[$class];
        $values = $this->objectFactory->read($entity);

        $identity = [];

        foreach ($classMetadata->idProperties as $idProperty) {
            if (! isset($values[$idProperty])) {
                throw new Exception\NoIdentityException('The entity has no identity.');
            }

            $identity[$idProperty] = $values[$idProperty];
        }

        return $identity;
    }

    /**
     * Returns the identity of the given identity, as a list of scalar values.
     *
     * @psalm-param array<string, mixed> $identity
     *
     * @psalm-return list<int|string>
     *
     * @param EntityMetadata $classMetadata The entity class metadata.
     * @param array          $identity      The object's identity, as a map of property name to value.
     *                                      Must contain a valid entry for each identity property.
     *
     * @return array The identity, as a list of int or string values.
     *
     * @throws Exception\NoIdentityException If an entity with no identity is part of the given identity.
     */
    private function getScalarIdentity(EntityMetadata $classMetadata, array $identity) : array
    {
        $result = [];

        foreach ($classMetadata->idProperties as $idProperty) {
            $value = $identity[$idProperty];
            $propertyMapping = $classMetadata->propertyMappings[$idProperty];

            if ($propertyMapping instanceof EntityMapping) {
                $targetClassMetadata = $propertyMapping->classMetadata;

                /** @var object $value */
                $targetId = $this->getIdentity($targetClassMetadata->className, $value);

                foreach ($this->getScalarIdentity($targetClassMetadata, $targetId) as $value) {
                    $result[] = $value;
                }
            } else {
                // This is guaranteed to be an IntMapping or StringMapping, we can use the value directly.
                /** @var int|string $value */
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Returns the FQCN of the given object.
     *
     * @psalm-return class-string
     *
     * @throws Exception\UnknownEntityClassException If the object is not a known entity.
     */
    private function getEntityClass(object $entity) : string
    {
        if ($entity instanceof Proxy) {
            $class = get_parent_class($entity);
        } else {
            $class = get_class($entity);
        }

        if (! isset($this->classMetadata[$class])) {
            throw Exception\UnknownEntityClassException::unknownEntityClass($class);
        }

        return $class;
    }
}
