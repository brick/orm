<?php

declare(strict_types=1);

namespace REPO_NAMESPACE;

use Brick\ORM\Gateway;
use Brick\ORM\LockMode;

use IMPORTS;

/**
 * Repository for CLASS_NAME entities.
 * This class is generated automatically. Please do not edit.
 */
class CLASS_NAMERepository
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

    public function load($IDENTITY_PROPS, int $lockMode = LockMode::NONE, string ...$props) : ?CLASS_NAME
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->load(CLASS_NAME::class, IDENTITY_ARRAY, $lockMode, ...$props);
    }

    public function getPlaceholder($IDENTITY_PROPS) : CLASS_NAME
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->getPlaceholder(CLASS_NAME::class, IDENTITY_ARRAY);
    }

    public function exists(CLASS_NAME $ENTITY_PROP_NAME) : bool
    {
        return $this->gateway->exists(CLASS_NAME::class, $ENTITY_PROP_NAME);
    }

    public function existsIdentity($IDENTITY_PROPS) : bool
    {
        return $this->gateway->existsIdentity(CLASS_NAME::class, IDENTITY_ARRAY);
    }

    public function save(CLASS_NAME $ENTITY_PROP_NAME) : void
    {
        $this->gateway->save($ENTITY_PROP_NAME);
    }

    public function update(CLASS_NAME $ENTITY_PROP_NAME) : void
    {
        $this->gateway->update($ENTITY_PROP_NAME);
    }

    public function remove(CLASS_NAME $ENTITY_PROP_NAME) : void
    {
        $this->gateway->remove(CLASS_NAME::class, $ENTITY_PROP_NAME);
    }

    public function removeIdentity($IDENTITY_PROPS) : void
    {
        $this->gateway->removeIdentity(CLASS_NAME::class, IDENTITY_ARRAY);
    }
}
