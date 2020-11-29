<?php

declare(strict_types=1);

namespace Brick\ORM;

class QueryPredicate
{
    private string $property;

    private string $operator;

    private mixed $value;

    /**
     * @param string $property The property name, optionally including dots for sub-properties.
     * @param string $operator The operator, such as "=", "!=" or ">".
     * @param mixed  $value    The value to compare against.
     *
     * @throws \InvalidArgumentException If the operator is invalid.
     */
    public function __construct(string $property, string $operator, mixed $value)
    {
        if (! in_array($operator, ['=', '!=',  '>', '<', '>=', '<='])) {
            throw new \InvalidArgumentException(sprintf('Unknown operator "%s".', $operator));
        }

        $this->property = $property;
        $this->operator = $operator;
        $this->value    = $value;
    }

    public function getProperty() : string
    {
        return $this->property;
    }

    public function getOperator() : string
    {
        return $this->operator;
    }

    public function getValue() : mixed
    {
        return $this->value;
    }
}
