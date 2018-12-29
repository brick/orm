<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models\Event\UserEvent;

use Brick\ORM\Tests\Resources\Models\Event\UserEvent;
use Brick\ORM\Tests\Resources\Models\GeoAddress;
use Brick\ORM\Tests\Resources\Models\User;

class EditUserDeliveryAddressEvent extends UserEvent
{
    /**
     * This property purposefully has the same name and different type as another property in a sibling class.
     *
     * @var GeoAddress
     */
    protected $newAddress;

    /**
     * @param User       $user
     * @param GeoAddress $newDeliveryAddress
     */
    public function __construct(User $user, GeoAddress $newDeliveryAddress)
    {
        parent::__construct($user);

        $this->newAddress = $newDeliveryAddress;
    }
}
