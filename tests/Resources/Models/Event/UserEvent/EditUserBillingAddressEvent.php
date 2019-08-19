<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models\Event\UserEvent;

use Brick\ORM\Tests\Resources\Models\Address;
use Brick\ORM\Tests\Resources\Models\Event\UserEvent;
use Brick\ORM\Tests\Resources\Models\User;

class EditUserBillingAddressEvent extends UserEvent
{
    /**
     * This property purposefully has the same name and different type as another property in a sibling class.
     */
    protected Address $newAddress;

    public function __construct(User $user, Address $newBillingAddress)
    {
        parent::__construct($user);

        $this->newAddress = $newBillingAddress;
    }
}
