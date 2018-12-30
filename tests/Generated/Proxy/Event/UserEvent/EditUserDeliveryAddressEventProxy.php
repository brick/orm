<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Generated\Proxy\Event\UserEvent;

use Brick\ORM\Gateway;
use Brick\ORM\LockMode;
use Brick\ORM\Proxy;

use Brick\ORM\Tests\Resources\Models\Event\UserEvent\EditUserDeliveryAddressEvent;

/**
 * Proxy for EditUserDeliveryAddressEvent entities.
 * This class is generated automatically. Please do not edit.
 */
class EditUserDeliveryAddressEventProxy extends EditUserDeliveryAddressEvent implements Proxy
{
    private const __NON_ID_PROPERTIES = ['newAddress', 'user', 'time'];

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
     * @param Gateway  $gateway  The gateway.
     * @param array    $identity The identity, as a map of property name to value.
     */
    public function __construct(Gateway $gateway, array $identity)
    {
        $this->__gateway = $gateway;
        $this->__identity = $identity;

        foreach ($identity as $prop => $value) {
            $this->{$prop} = $value;
        }

        unset(
            $this->newAddress,
            $this->user,
            $this->time
        );
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (! $this->__isInitialized) {
            $loadProps = [];

            foreach (self::__NON_ID_PROPERTIES as $prop) {
                if (! isset($this->{$prop})) { // exclude initialized properties
                    $loadProps[] = $prop;
                }
            }

            if ($loadProps) {
                $propValues = $this->__gateway->loadProps(EditUserDeliveryAddressEvent::class, $this->__identity, $loadProps);

                if ($propValues === null) {
                    // @todo custom exception class + show identity (using scalars?) in error message
                    throw new \RuntimeException(sprintf('Proxied entity does not exist.'));
                }

                foreach ($propValues as $prop => $value) {
                    $this->{$prop} = $value;
                }
            }

            $this->__isInitialized = true;
        }

        return $this->{$name};
    }
}
