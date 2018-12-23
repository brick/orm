<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models;

/**
 * An embeddable class, composed of built-in types and entities.
 */
class Address
{
    /**
     * @var string
     */
    protected $street;

    /**
     * @var string
     */
    protected $city;

    /**
     * This property is mapped to a "zipcode" field.
     *
     * @var string|null
     */
    protected $postcode;

    /**
     * @var Country
     */
    protected $country;

    /**
     * @var bool
     */
    protected $isPoBox;

    /**
     * @param string  $street
     * @param string  $city
     * @param string  $postcode
     * @param Country $country
     * @param bool    $isPoBox
     */
    public function __construct(string $street, string $city, string $postcode, Country $country, bool $isPoBox)
    {
        $this->street   = $street;
        $this->city     = $city;
        $this->postcode = $postcode;
        $this->country  = $country;
        $this->isPoBox  = $isPoBox;
    }

    /**
     * @return string
     */
    public function getStreet() : string
    {
        return $this->street;
    }

    /**
     * @return string
     */
    public function getCity() : string
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getPostcode() : string
    {
        return $this->postcode;
    }

    /**
     * @return Country
     */
    public function getCountry() : Country
    {
        return $this->country;
    }

    /**
     * @return bool
     */
    public function isPoBox() : bool
    {
        return $this->isPoBox;
    }
}
