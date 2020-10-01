<?php

declare(strict_types=1);

namespace Brick\ORM;

class Query
{
    private string $className;

    /**
     * The properties to load, or null to load the full entity.
     *
     * @var string[]|null
     */
    private ?array $properties = null;

    /**
     * @var QueryPredicate[]
     */
    private array $predicates = [];

    /**
     * @var QueryOrderBy[]
     */
    private array $orderBy = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /**
     * @param string $className
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * @param string ...$properties
     *
     * @return Query
     */
    public function setProperties(string ...$properties) : Query
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * @param string $property
     * @param string $operator
     * @param mixed  $value
     *
     * @return Query
     *
     * @throws \InvalidArgumentException If the operator is invalid.
     */
    public function addPredicate(string $property, string $operator, mixed $value) : Query
    {
        $this->predicates[] = new QueryPredicate($property, $operator, $value);

        return $this;
    }

    /**
     * @param string $property  The property to order by.
     * @param string $direction The order direction, 'ASC' or 'DESC'.
     *
     * @return Query
     *
     * @throws \InvalidArgumentException If the order direction is invalid.
     */
    public function addOrderBy(string $property, string $direction = 'ASC') : Query
    {
        $this->orderBy[] = new QueryOrderBy($property, $direction);

        return $this;
    }

    /**
     * @param int $limit
     * @param int $offset
     *
     * @return Query
     */
    public function setLimit(int $limit, int $offset = 0) : Query
    {
        $this->limit  = $limit;
        $this->offset = $offset;

        return $this;
    }

    /**
     * @return string
     */
    public function getClassName() : string
    {
        return $this->className;
    }

    /**
     * @return string[]|null
     */
    public function getProperties() : ?array
    {
        return $this->properties;
    }

    /**
     * @return QueryPredicate[]
     */
    public function getPredicates() : array
    {
        return $this->predicates;
    }

    /**
     * @return QueryOrderBy[]
     */
    public function getOrderBy() : array
    {
        return $this->orderBy;
    }

    /**
     * @return int|null
     */
    public function getLimit() : ?int
    {
        return $this->limit;
    }

    /**
     * @return int|null
     */
    public function getOffset() : ?int
    {
        return $this->offset;
    }
}
