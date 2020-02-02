<?php

declare(strict_types=1);

namespace Brick\ORM;

/**
 * Low-level class to build SELECT queries.
 *
 * @internal
 */
class SelectQueryBuilder
{
    /**
     * The fields to SELECT.
     *
     * @var string[]
     */
    private array $selectFields;

    /**
     * The table name.
     */
    private string $tableName;

    /**
     * An optional table alias.
     */
    private ?string $tableAlias;

    private array $joins = [];

    /**
     * @var string[]
     */
    private array $whereConditions = [];

    /**
     * @var string[]
     */
    private array $orderBy = [];

    private string $limit = '';

    private int $options = 0;

    /**
     * @param string[]    $selectFields The fields or expressions to SELECT.
     * @param string      $tableName    The table name.
     * @param string|null $tableAlias   An optional table alias.
     */
    public function __construct(array $selectFields, string $tableName, ?string $tableAlias = null)
    {
        $this->selectFields = $selectFields;
        $this->tableName    = $tableName;
        $this->tableAlias   = $tableAlias;
    }

    /**
     * @param string   $joinType       The JOIN type, such as INNER or LEFT.
     * @param string   $tableName      The table name.
     * @param string   $tableAlias     The table alias.
     * @param string[] $joinConditions The list of A=B join conditions.
     *
     * @return void
     */
    public function addJoin(string $joinType, string $tableName, string $tableAlias, array $joinConditions) : void
    {
        $this->joins[] = ' ' . $joinType . ' JOIN ' . $tableName .
            ' AS ' . $tableAlias .
            ' ON ' . implode(' AND ', $joinConditions);
    }

    /**
     * Adds WHERE conditions to be AND'ed to the current conditions.
     *
     * The conditions will be AND'ed or OR'ed together, according to the given operator, and AND'ed as a whole to the
     * existing conditions.
     *
     * @param string[] $whereConditions The WHERE conditions.
     * @param string   $operator        The operator, 'AND' or 'OR'.
     *
     * @return void
     */
    public function addWhereConditions(array $whereConditions, string $operator = 'AND') : void
    {
        $parentheses = ($operator === 'OR' && count($whereConditions) > 1);

        $whereConditions = implode(' ' . $operator . ' ', $whereConditions);

        if ($parentheses) {
            $whereConditions = '(' . $whereConditions . ')';
        }

        $this->whereConditions[] = $whereConditions;
    }

    /**
     * @param string $expression The expression to order by.
     * @param string $direction  The order direction, 'ASC' or 'DESC'.
     *
     * @return void
     */
    public function addOrderBy(string $expression, string $direction = 'ASC') : void
    {
        $this->orderBy[] = $expression . ' ' . $direction;
    }

    /**
     * @param int $limit
     * @param int $offset
     *
     * @return void
     */
    public function setLimit(int $limit, int $offset = 0) : void
    {
        $this->limit = ' LIMIT ' . $limit;

        if ($offset !== 0) {
            $this->limit .= ' OFFSET ' . $offset;
        }
    }

    /**
     * @param int $options A bitmask of options.
     *
     * @return void
     */
    public function setOptions(int $options) : void
    {
        $this->options = $options;
    }

    /**
     * @return string
     */
    public function build() : string
    {
        $query = 'SELECT ' . implode(', ', $this->selectFields) . ' FROM ' . $this->tableName;

        if ($this->tableAlias !== null) {
            $query .= ' AS ' . $this->tableAlias;
        }

        foreach ($this->joins as $join) {
            $query .= $join;
        }

        if ($this->whereConditions) {
            $query .= ' WHERE ' . implode(' AND ', $this->whereConditions);
        }

        if ($this->orderBy) {
            $query .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        $query .= $this->limit;

        // @todo lock mode syntax is MySQL / PostgreSQL only

        if ($this->options & Options::LOCK_READ) {
            $query .= ' FOR SHARE';
        } elseif ($this->options & Options::LOCK_WRITE) {
            $query .= ' FOR UPDATE';
        }

        if ($this->options & Options::SKIP_LOCKED) {
            $query .= ' SKIP LOCKED';
        }

        if ($this->options & Options::NOWAIT) {
            $query .= ' NOWAIT';
        }

        return $query;
    }
}
