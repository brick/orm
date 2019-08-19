<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models\Event\UserEvent;

use Brick\ORM\Tests\Resources\Models\Event\UserEvent;
use Brick\ORM\Tests\Resources\Models\User;

class EditUserNameEvent extends UserEvent
{
    protected string $newName;

    public function __construct(User $user, string $newName)
    {
        parent::__construct($user);

        $this->newName = $newName;
    }

    public function getNewName() : string
    {
        return $this->newName;
    }
}
