<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Generated\Proxy\Event\CountryEvent;

use Brick\ORM\Gateway;
use Brick\ORM\LockMode;
use Brick\ORM\Proxy;

use Brick\ORM\Tests\Resources\Models\Event\CountryEvent\EditCountryNameEvent;

/**
 * Proxy for EditCountryNameEvent entities.
 * This class is generated automatically. Please do not edit.
 */
class EditCountryNameEventProxy extends EditCountryNameEvent implements Proxy
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

        unset(
            $this->newName,
            $this->country,
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
            // @todo should this only load non-initialized properties? Currently it loads everything, overwriting initialized properties!
            $propValues = $this->__gateway->loadProps(EditCountryNameEvent::class, $this->__identity, LockMode::NONE, null);

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
