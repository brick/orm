<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Generated\Repository;

use Brick\ORM\Gateway;
use Brick\ORM\LockMode;

use Brick\ORM\Tests\Resources\Models\Country;

/**
 * Repository for Country entities.
 * This class is generated automatically. Please do not edit.
 */
class CountryRepository
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

    public function load(string $code, int $lockMode = LockMode::NONE, ?array $props = null) : ?Country
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->load(Country::class, ['code' => $code], $lockMode, $props);
    }

    public function getPlaceholder(string $code) : Country
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->getPlaceholder(Country::class, ['code' => $code]);
    }

    public function exists(Country $country) : bool
    {
        return $this->gateway->exists(Country::class, $country);
    }

    public function existsIdentity(string $code) : bool
    {
        return $this->gateway->existsIdentity(Country::class, ['code' => $code]);
    }

    public function save(Country $country) : void
    {
        $this->gateway->save($country);
    }

    public function update(Country $country) : void
    {
        $this->gateway->update($country);
    }

    public function remove(Country $country) : void
    {
        $this->gateway->remove(Country::class, $country);
    }

    public function removeIdentity(string $code) : void
    {
        $this->gateway->removeIdentity(Country::class, ['code' => $code]);
    }
}
