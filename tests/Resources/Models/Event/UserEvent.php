<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models\Event;

use Brick\ORM\Tests\Resources\Models\Event;
use Brick\ORM\Tests\Resources\Models\User;

abstract class UserEvent extends Event
{
    protected User $user;

    public function __construct(User $user)
    {
        parent::__construct();

        $this->user = $user;
    }

    public function getUser() : User
    {
        return $this->user;
    }
}
