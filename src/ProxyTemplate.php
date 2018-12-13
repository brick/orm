<?php

declare(strict_types=1);

namespace PROXY_NAMESPACE;

use Brick\ORM\Gateway;
use Brick\ORM\LockMode;
use Brick\ORM\Proxy;

use IMPORTS;

/**
 * Proxy for CLASS_NAME entities.
 * This class is generated automatically. Please do not edit.
 */
class CLASS_NAMEProxy extends CLASS_NAME implements Proxy
{
    /**
     * @var Gateway
     */
    private $__gateway;

    /**
     * @var array
     */
    private $__identity;

    /**
     * @var bool
     */
    private $__isInitialized = false;

    /**
     * Class constructor.
     *
     * @param Gateway $gateway The gateway.
     * @param array   $id      The identity, as a map of property name to value.
     */
    public function __construct(Gateway $gateway, array $id)
    {
        $this->__gateway = $gateway;
        $this->__identity = $id;

        foreach ($id as $prop => $value) {
            $this->{$prop} = $value;
        }

        unset($UNSET_NON_ID_PROPS);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (! $this->__isInitialized) {
            $propValues = $this->__gateway->loadProps(CLASS_NAME::class, $this->__identity, null, LockMode::NONE);

            if ($propValues === null) {
                // @todo custom exception class + show identity (using scalars?) in error message
                throw new \RuntimeException(sprintf('Proxied entity does not exist.'));
            }

            foreach ($propValues as $prop => $value) {
                $this->{$prop} = $value;
            }

            $this->__isInitialized = true;
        }

        return $this->{$name};
    }
}
