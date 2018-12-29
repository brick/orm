<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Generated\Repository;

use Brick\ORM\Gateway;
use Brick\ORM\LockMode;

use Brick\ORM\Tests\Resources\Models\Event;

/**
 * Repository for Event entities.
 * This class is generated automatically. Please do not edit.
 */
class EventRepository
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

    public function load(int $id, int $lockMode = LockMode::NONE, ?array $props = null) : ?Event
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->load(Event::class, ['id' => $id], $lockMode, $props);
    }

    public function getPlaceholder(int $id) : Event
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->getPlaceholder(Event::class, ['id' => $id]);
    }

    public function exists(Event $event) : bool
    {
        return $this->gateway->exists(Event::class, $event);
    }

    public function existsIdentity(int $id) : bool
    {
        return $this->gateway->existsIdentity(Event::class, ['id' => $id]);
    }

    public function save(Event $event) : void
    {
        $this->gateway->save($event);
    }

    public function update(Event $event) : void
    {
        $this->gateway->update($event);
    }

    public function remove(Event $event) : void
    {
        $this->gateway->remove(Event::class, $event);
    }

    public function removeIdentity(int $id) : void
    {
        $this->gateway->removeIdentity(Event::class, ['id' => $id]);
    }
}