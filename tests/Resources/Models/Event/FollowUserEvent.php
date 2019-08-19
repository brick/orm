<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models\Event;

use Brick\ORM\Tests\Resources\Models\Event;
use Brick\ORM\Tests\Resources\Models\User;

class FollowUserEvent extends Event
{
    protected User $follower;

    protected User $followee;

    protected bool $isFollow;

    public function __construct(User $follower, User $followee, bool $isFollow)
    {
        parent::__construct();

        $this->follower = $follower;
        $this->followee = $followee;
        $this->isFollow = $isFollow;
    }

    public function getFollower() : User
    {
        return $this->follower;
    }

    public function getFollowee() : User
    {
        return $this->followee;
    }

    public function isFollow() : bool
    {
        return $this->isFollow;
    }
}
