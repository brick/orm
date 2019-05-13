<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\Tests\Resources\Models\Address;
use Brick\ORM\Tests\Resources\Models\Country;
use Brick\ORM\Tests\Resources\Models\User;

class ProxyTest extends AbstractTestCase
{
    /**
     * {@inheritdoc}
     */
    protected static function useProxies() : bool
    {
        return true;
    }

    /**
     * @return void
     */
    public function testLazyLoadingProxy() : void
    {
        // create the user

        $userId = (function() : int {
            $country = new Country('GB', 'United Kingdom');
            self::$countryRepository->save($country);

            $user = new User('John Smith');

            $billingAddress = new Address('123 Unknown Road', 'London', 'WC2E9XX', $country, false);
            $user->setBillingAddress($billingAddress);

            self::$userRepository->save($user);
            self::$logger->reset();

            return $user->getId();
        })();

        // reload the user

        $user = self::$userRepository->load($userId);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'SELECT a.id, a.name, a.street, a.city, a.zipcode, a.country_code, a.isPoBox, a.deliveryAddress_address_street, ' .
            'a.deliveryAddress_address_city, a.deliveryAddress_address_zipcode, a.deliveryAddress_address_country_code, ' .
            'a.deliveryAddress_address_isPoBox, ST_AsText(a.deliveryAddress_location), ST_SRID(a.deliveryAddress_location), ' .
            'a.lastEvent_type, a.lastEvent_id, a.data ' .
            'FROM User AS a WHERE a.id = ?',
            $userId
        );

        $country = $user->getBillingAddress()->getCountry();

        // Using an identity property: should be readily available and not trigger lazy initialization
        $this->assertSame('GB', $country->getCode());
        $this->assertDebugStatementCount(1);

        // Using a non-identity property: should initialize the proxy
        $this->assertSame('United Kingdom', $country->getName());
        $this->assertDebugStatementCount(2);
        $this->assertDebugStatement(1, 'SELECT a.name FROM Country AS a WHERE a.code = ?', 'GB');
    }
}
