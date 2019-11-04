<?php

declare(strict_types=1);

namespace REPO_NAMESPACE;

use Brick\ORM\Gateway;
use Brick\ORM\Options;

use IMPORTS;

/**
 * Repository for CLASS_NAME entities.
 * This class is generated automatically. Please do not edit.
 */
class CLASS_NAMERepository
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

    public function load($IDENTITY_PROPS, int $options = 0, string ...$props) : ?CLASS_NAME
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->load(CLASS_NAME::class, IDENTITY_ARRAY, $options, ...$props);
    }

    public function getReference($IDENTITY_PROPS) : CLASS_NAME
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->gateway->getReference(CLASS_NAME::class, IDENTITY_ARRAY);
    }

    public function exists(CLASS_NAME $ENTITY_PROP_NAME) : bool
    {
        return $this->gateway->exists($ENTITY_PROP_NAME);
    }

    public function existsIdentity($IDENTITY_PROPS) : bool
    {
        return $this->gateway->existsIdentity(CLASS_NAME::class, IDENTITY_ARRAY);
    }

    public function add(CLASS_NAME $ENTITY_PROP_NAME) : void
    {
        $this->gateway->add($ENTITY_PROP_NAME);
    }

    public function update(CLASS_NAME $ENTITY_PROP_NAME) : void
    {
        $this->gateway->update($ENTITY_PROP_NAME);
    }

    public function remove(CLASS_NAME $ENTITY_PROP_NAME) : void
    {
        $this->gateway->remove($ENTITY_PROP_NAME);
    }

    public function removeIdentity($IDENTITY_PROPS) : void
    {
        $this->gateway->removeIdentity(CLASS_NAME::class, IDENTITY_ARRAY);
    }
}
