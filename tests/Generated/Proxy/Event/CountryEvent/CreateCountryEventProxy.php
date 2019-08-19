<?php

declare(strict_types=1);

namespace Brick\ORM\Tests\Generated\Proxy\Event\CountryEvent;

use Brick\ORM\Exception\EntityNotFoundException;
use Brick\ORM\Gateway;
use Brick\ORM\Proxy;

use Brick\ORM\Tests\Resources\Models\Event\CountryEvent\CreateCountryEvent;

/**
 * Proxy for CreateCountryEvent entities.
 * This class is generated automatically. Please do not edit.
 */
class CreateCountryEventProxy extends CreateCountryEvent implements Proxy
{
    private const __NON_ID_PROPERTIES = ['country', 'time'];

    private Gateway $__gateway;

    private array $__identity;

    private array $__scalarIdentity;

    private bool $__isInitialized = false;

    /**
     * Class constructor.
     *
     * @param Gateway $gateway        The gateway.
     * @param array   $identity       The identity, as a map of property name to value.
     * @param array   $scalarIdentity The identity, as a list of scalar values.
     */
    public function __construct(Gateway $gateway, array $identity, array $scalarIdentity)
    {
        $this->__gateway = $gateway;
        $this->__identity = $identity;
        $this->__scalarIdentity = $scalarIdentity;

        foreach ($identity as $prop => $value) {
            $this->{$prop} = $value;
        }

        unset(
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
            $loadProps = [];

            foreach (self::__NON_ID_PROPERTIES as $prop) {
                if (! isset($this->{$prop})) { // exclude initialized properties
                    $loadProps[] = $prop;
                }
            }

            if ($loadProps) {
                $propValues = $this->__gateway->loadProps(CreateCountryEvent::class, $this->__identity, $loadProps);

                if ($propValues === null) {
                    throw EntityNotFoundException::entityNotFound(CreateCountryEvent::class, $this->__scalarIdentity);
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
