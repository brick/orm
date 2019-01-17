<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Resources\Models\Event;

use Brick\ORM\Tests\Resources\Models\Event;
use Brick\ORM\Tests\Resources\Models\User;

class FollowUserEvent extends Event
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
     * @var bool
     */
    protected $isFollow;

    /**
     * @param User $follower
     * @param User $followee
     * @param bool $isFollow
     */
    public function __construct(User $follower, User $followee, bool $isFollow)
    {
        parent::__construct();

        $this->follower = $follower;
        $this->followee = $followee;
        $this->isFollow = $isFollow;
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
     * @return bool
     */
    public function isFollow() : bool
    {
        return $this->isFollow;
    }
}
