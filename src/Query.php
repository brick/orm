<?php

declare(strict_types=1);

namespace Brick\ORM;

use InvalidArgumentException;

use function array_values;

class Query
{
    /**
     * @var class-string
     */
    private string $className;

    /**
     * The properties to load, or null to load the full entity.
     *
     * @var list<string>|null
     */
    private ?array $properties = null;

    /**
     * @var list<QueryPredicate>
     */
    private array $predicates = [];

    /**
     * @var list<QueryOrderBy>
     */
    private array $orderBy = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /**
     * @param class-string $className
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function setProperties(string ...$properties): Query
    {
        $this->properties = array_values($properties);

        return $this;
    }

    /**
     * @throws InvalidArgumentException If the operator is invalid.
     */
    public function addPredicate(string $property, string $operator, mixed $value): Query
    {
        $this->predicates[] = new QueryPredicate($property, $operator, $value);

        return $this;
    }

    /**
     * @param string $property  The property to order by.
     * @param string $direction The order direction, 'ASC' or 'DESC'.
     *
     * @throws InvalidArgumentException If the order direction is invalid.
     */
    public function addOrderBy(string $property, string $direction = 'ASC'): Query
    {
        $this->orderBy[] = new QueryOrderBy($property, $direction);

        return $this;
    }

    public function setLimit(int $limit, int $offset = 0): Query
    {
        $this->limit = $limit;
        $this->offset = $offset;

        return $this;
    }

    /**
     * @return class-string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return list<string>|null
     */
    public function getProperties(): ?array
    {
        return $this->properties;
    }

    /**
     * @return list<QueryPredicate>
     */
    public function getPredicates(): array
    {
        return $this->predicates;
    }

    /**
     * @return list<QueryOrderBy>
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }
}
