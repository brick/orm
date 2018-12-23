<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models;

/**
 * An entity with a composite identity composed of 2 other entities (follower, followee).
 */
class Follow
{
    /**
     * @var User
     */
    protected $follower;

    /**
     * @var User
     */
    protected $followee;

    /**
     * @var int
     */
    protected $since;

    /**
     * @param User $follower
     * @param User $followee
     */
    public function __construct(User $follower, User $followee)
    {
        $this->follower = $follower;
        $this->followee = $followee;
        $this->since    = time();
    }

    /**
     * @return User
     */
    public function getFollower() : User
    {
        return $this->follower;
    }

    /**
     * @return User
     */
    public function getFollowee() : User
    {
        return $this->followee;
    }

    /**
     * @return int
     */
    public function getSince() : int
    {
        return $this->since;
    }
}
