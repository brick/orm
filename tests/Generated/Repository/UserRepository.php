<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Generated\Repository;

use Brick\ORM\Gateway;
use Brick\ORM\LockMode;

use Brick\ORM\Tests\Resources\Models\User;

/**
 * Repository for User entities.
 * This class is generated automatically. Please do not edit.
 */
class UserRepository
{
    /**
     * @var Gateway
     */
    private $gateway;

    /**
     * Class constructor.
     *
     * @param Gateway $gateway
     */
    public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function load(int $id, int $lockMode = LockMode::NONE, string ...$props) : ?User
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->load(User::class, ['id' => $id], $lockMode, ...$props);
    }

    public function getPlaceholder(int $id) : User
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->getPlaceholder(User::class, ['id' => $id]);
    }

    public function exists(User $user) : bool
    {
        return $this->gateway->exists($user);
    }

    public function existsIdentity(int $id) : bool
    {
        return $this->gateway->existsIdentity(User::class, ['id' => $id]);
    }

    public function save(User $user) : void
    {
        $this->gateway->save($user);
    }

    public function update(User $user) : void
    {
        $this->gateway->update($user);
    }

    public function remove(User $user) : void
    {
        $this->gateway->remove($user);
    }

    public function removeIdentity(int $id) : void
    {
        $this->gateway->removeIdentity(User::class, ['id' => $id]);
    }
}
