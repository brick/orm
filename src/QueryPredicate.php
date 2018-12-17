<?php

declare(strict_types=1);

namespace Brick\ORM;

class QueryPredicate
{
    /**
     * @var string
     */
    private $property;

    /**
     * @var string
     */
    private $operator;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @param string $property The property name, optionally including dots for sub-properties.
     * @param string $operator The operator, such as "=", "!=" or ">".
     * @param mixed  $value    The value to compare against.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $property, string $operator, $value)
    {
        if (! in_array($operator, ['=', '!=',  '>', '<', '>=', '<='])) {
            throw new \InvalidArgumentException(sprintf('Unknown operator "%s".', $operator));
        }

        $this->property = $property;
        $this->operator = $operator;
        $this->value    = $value;
    }

    /**
     * @return string
     */
    public function getProperty() : string
    {
        return $this->property;
    }

    /**
     * @return string
     */
    public function getOperator() : string
    {
        return $this->operator;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
