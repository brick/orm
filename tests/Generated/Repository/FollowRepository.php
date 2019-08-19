<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Generated\Repository;

use Brick\ORM\Gateway;
use Brick\ORM\Options;

use Brick\ORM\Tests\Resources\Models\Follow,
    Brick\ORM\Tests\Resources\Models\User;

/**
 * Repository for Follow entities.
 * This class is generated automatically. Please do not edit.
 */
class FollowRepository
{
    private Gateway $gateway;

    /**
     * Class constructor.
     *
     * @param Gateway $gateway
     */
    public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function load(User $follower, User $followee, int $options = 0, string ...$props) : ?Follow
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->load(Follow::class, ['follower' => $follower, 'followee' => $followee], $options, ...$props);
    }

    public function getReference(User $follower, User $followee) : Follow
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->getReference(Follow::class, ['follower' => $follower, 'followee' => $followee]);
    }

    public function exists(Follow $follow) : bool
    {
        return $this->gateway->exists($follow);
    }

    public function existsIdentity(User $follower, User $followee) : bool
    {
        return $this->gateway->existsIdentity(Follow::class, ['follower' => $follower, 'followee' => $followee]);
    }

    public function save(Follow $follow) : void
    {
        $this->gateway->save($follow);
    }

    public function update(Follow $follow) : void
    {
        $this->gateway->update($follow);
    }

    public function remove(Follow $follow) : void
    {
        $this->gateway->remove($follow);
    }

    public function removeIdentity(User $follower, User $followee) : void
    {
        $this->gateway->removeIdentity(Follow::class, ['follower' => $follower, 'followee' => $followee]);
    }
}
