<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models;

/**
 * An embeddable class, composed of built-in types and entities.
 */
class Address
{
    protected string $street;

    protected string $city;

    /**
     * This property is mapped to a "zipcode" field.
     */
    protected ?string $postcode;

    protected Country $country;

    protected bool $isPoBox;

    public function __construct(string $street, string $city, string $postcode, Country $country, bool $isPoBox)
    {
        $this->street   = $street;
        $this->city     = $city;
        $this->postcode = $postcode;
        $this->country  = $country;
        $this->isPoBox  = $isPoBox;
    }

    public function getStreet() : string
    {
        return $this->street;
    }

    public function getCity() : string
    {
        return $this->city;
    }

    public function getPostcode() : string
    {
        return $this->postcode;
    }

    public function getCountry() : Country
    {
        return $this->country;
    }

    public function isPoBox() : bool
    {
        return $this->isPoBox;
    }
}
