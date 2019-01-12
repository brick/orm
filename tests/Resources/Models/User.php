<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models;

use Brick\ORM\Tests\Resources\Models\Event\UserEvent;

/**
 * An entity with an auto-increment integer identity, composed of built-in types, other entities, embeddables.
 */
class User
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * An embedded address, with no field name prefix.
     *
     * @var Address|null
     */
    protected $billingAddress;

    /**
     * An embedded geo address, with a field name prefix.
     *
     * @var GeoAddress|null
     */
    protected $deliveryAddress;

    /**
     * A reference to a entity in an inheritance hierarchy, requiring the discriminator value in the User table to know
     * the correct target class.
     *
     * @var UserEvent|null
     */
    protected $lastEvent;

    /**
     * A transient property, that should not be persisted.
     *
     * @var array
     */
    protected $transient = [];

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name) : void
    {
        $this->name = $name;
    }

    /**
     * @return Address|null
     */
    public function getBillingAddress() : ?Address
    {
        return $this->billingAddress;
    }

    /**
     * @param Address|null $billingAddress
     *
     * @return void
     */
    public function setBillingAddress(?Address $billingAddress) : void
    {
        $this->billingAddress = $billingAddress;
    }

    /**
     * @return GeoAddress|null
     */
    public function getDeliveryAddress() : ?GeoAddress
    {
        return $this->deliveryAddress;
    }

    /**
     * @param GeoAddress|null $deliveryAddress
     *
     * @return void
     */
    public function setDeliveryAddress(?GeoAddress $deliveryAddress) : void
    {
        $this->deliveryAddress = $deliveryAddress;
    }

    /**
     * @return array
     */
    public function getTransient() : array
    {
        return $this->transient;
    }
}
