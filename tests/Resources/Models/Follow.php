<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models;

/**
 * An entity with a composite identity composed of 2 other entities (follower, followee).
 */
class Follow
{
    protected User $follower;

    protected User $followee;

    protected int $since;

    public function __construct(User $follower, User $followee)
    {
        $this->follower = $follower;
        $this->followee = $followee;
        $this->since    = time();
    }

    public function getFollower() : User
    {
        return $this->follower;
    }

    public function getFollowee() : User
    {
        return $this->followee;
    }

    public function getSince() : int
    {
        return $this->since;
    }
}
