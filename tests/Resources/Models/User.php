<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models;

use Brick\ORM\Tests\Resources\Models\Event\UserEvent;

/**
 * An entity with an auto-increment integer identity, composed of built-in types, other entities, embeddables.
 */
class User
{
    protected int $id;

    protected string $name;

    /**
     * An embedded address, with no field name prefix.
     */
    protected ?Address $billingAddress = null;

    /**
     * An embedded geo address, with a field name prefix.
     */
    protected ?GeoAddress $deliveryAddress = null;

    /**
     * A reference to a entity in an inheritance hierarchy, requiring the discriminator value in the User table to know
     * the correct target class.
     */
    protected ?UserEvent $lastEvent = null;

    /**
     * A property mapped to JSON datatype.
     */
    protected array $data = ['any' => 'data'];

    /**
     * A transient property, that should not be persisted.
     */
    protected array $transient = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function setName(string $name) : void
    {
        $this->name = $name;
    }

    public function getBillingAddress() : ?Address
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?Address $billingAddress) : void
    {
        $this->billingAddress = $billingAddress;
    }

    public function getDeliveryAddress() : ?GeoAddress
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?GeoAddress $deliveryAddress) : void
    {
        $this->deliveryAddress = $deliveryAddress;
    }

    public function getData() : array
    {
        return $this->data;
    }

    public function getTransient() : array
    {
        return $this->transient;
    }
}
