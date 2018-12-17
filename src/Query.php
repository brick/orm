<?php

declare(strict_types=1);

namespace Brick\ORM;

class Query
{
    /**
     * @var string
     */
    private $className;

    /**
     * The properties to load, or null to load the full entity.
     *
     * @var string[]|null
     */
    private $properties;

    /**
     * @var QueryPredicate[]
     */
    private $predicates = [];

    /**
     * A map of property name to 'ASC' or 'DESC'.
     *
     * @var array
     */
    private $orderBy = [];

    /**
     * @var int|null
     */
    private $limit;

    /**
     * @var int|null
     */
    private $offset;

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
     */
    public function addPredicate(string $property, string $operator, $value) : Query
    {
        $this->predicates[] = new QueryPredicate($property, $operator, $value);

        return $this;
    }

    /**
     * @param array $orderBy A map of property name to 'ASC' or 'DESC'.
     *
     * @return Query
     */
    public function setOrderBy(array $orderBy) : Query
    {
        $this->orderBy = $orderBy;

        return $this;
    }

    /**
     * @param int      $limit
     * @param int|null $offset
     *
     * @return Query
     */
    public function setLimit(int $limit, ?int $offset = null) : Query
    {
        $this->limit = $limit;
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
     * @return string[]
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
    public function getOffset(): ?int
    {
        return $this->offset;
    }
}
