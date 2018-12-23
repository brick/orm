<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\Tests\Resources\Models\Address;
use Brick\ORM\Tests\Resources\Models\Country;
use Brick\ORM\Tests\Resources\Models\GeoAddress;
use Brick\ORM\Tests\Resources\Models\User;
use Brick\ORM\Tests\Resources\Objects\Geometry;

class GatewayTest extends AbstractTestCase
{
    public function testSaveCountry() : void
    {
        $country = new Country('GB', 'United Kingdom');
        self::$countryRepository->save($country);

        $this->assertDebugStatementCount(1);

        $this->assertDebugStatement(0, 'INSERT INTO Country (code, name) VALUES (?, ?)', ['GB', 'United Kingdom']);
    }

    /**
     * @depends testSaveCountry
     */
    public function testLoadUnknownCountry() : void
    {
        $this->assertNull(self::$countryRepository->load('XX'));
    }

    /**
     * @depends testLoadUnknownCountry
     *
     * @return Country
     */
    public function testLoadCountry() : Country
    {
        $country = self::$countryRepository->load('GB');

        $this->assertInstanceOf(Country::class, $country);
        $this->assertSame('GB', $country->getCode());
        $this->assertSame('United Kingdom', $country->getName());

        $this->assertDebugStatementCount(1);

        $this->assertDebugStatement(0, 'SELECT name FROM Country WHERE code = ?', ['GB']);

        return $country;
    }

    /**
     * @depends testLoadCountry
     *
     * @param Country $country
     *
     * @return User
     */
    public function testSaveUser(Country $country) : User
    {
        $user = new User('John Smith');

        $billingAddress = new Address('123 Unknown Road', 'London', 'WC2E9XX', $country, false);
        $user->setBillingAddress($billingAddress);

        self::$userRepository->save($user);

        $this->assertDebugStatementCount(1);

        $this->assertDebugStatement(0,
            'INSERT INTO User (id, name, street, city, zipcode, country_code, isPoBox, ' .
            'deliveryAddress_address_street, deliveryAddress_address_city, deliveryAddress_address_zipcode, ' .
            'deliveryAddress_address_country_code, deliveryAddress_address_isPoBox, deliveryAddress_location) ' .
            'VALUES (NULL, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL)',
            ['John Smith', '123 Unknown Road', 'London', 'WC2E9XX', 'GB', false]
        );

        // User ID must be set after saving
        $this->assertIsInt($user->getId());

        return $user;
    }

    /**
     * @depends testSaveUser
     *
     * @param User $user
     *
     * @return int
     */
    public function testUpdateUser(User $user) : int
    {
        $address = $user->getBillingAddress();
        $location = new Geometry('POINT (51 0)', 4326);

        $user->setDeliveryAddress(new GeoAddress($address, $location));
        self::$userRepository->update($user);

        $this->assertDebugStatementCount(1);

        $this->assertDebugStatement(0,
            'UPDATE User SET name = ?, street = ?, city = ?, zipcode = ?, country_code = ?, isPoBox = ?, ' .
            'deliveryAddress_address_street = ?, deliveryAddress_address_city = ?, ' .
            'deliveryAddress_address_zipcode = ?, deliveryAddress_address_country_code = ?, ' .
            'deliveryAddress_address_isPoBox = ?, deliveryAddress_location = ST_GeomFromText(?, ?) ' .
            'WHERE id = ?',
            [
                'John Smith',
                '123 Unknown Road', 'London', 'WC2E9XX', 'GB', false,
                '123 Unknown Road', 'London', 'WC2E9XX', 'GB', false,
                'POINT (51 0)', 4326, $user->getId()
            ]
        );

        return $user->getId();
    }

    /**
     * @depends testUpdateUser
     *
     * @param int $userId
     *
     * @return void
     */
    public function testLoadUser(int $userId) : void
    {
        $user = self::$userRepository->load($userId);

        $this->assertDebugStatementCount(1);

        $this->assertDebugStatement(0,
            'SELECT name, street, city, zipcode, country_code, isPoBox, ' .
            'deliveryAddress_address_street, deliveryAddress_address_city, deliveryAddress_address_zipcode, ' .
            'deliveryAddress_address_country_code, deliveryAddress_address_isPoBox, ' .
            'ST_AsText(deliveryAddress_location), ST_SRID(deliveryAddress_location) ' .
            'FROM User WHERE id = ?',
            [1]
        );

        $this->assertSame('John Smith', $user->getName());
        $this->assertSame('123 Unknown Road', $user->getBillingAddress()->getStreet());
        $this->assertSame('London', $user->getBillingAddress()->getCity());
        $this->assertSame('WC2E9XX', $user->getBillingAddress()->getPostcode());
        $this->assertSame('GB', $user->getBillingAddress()->getCountry()->getCode());
        $this->assertSame('123 Unknown Road', $user->getDeliveryAddress()->getAddress()->getStreet());
        $this->assertSame('London', $user->getDeliveryAddress()->getAddress()->getCity());
        $this->assertSame('WC2E9XX', $user->getDeliveryAddress()->getAddress()->getPostcode());
        $this->assertSame('GB', $user->getDeliveryAddress()->getAddress()->getCountry()->getCode());
        $this->assertSame('POINT(51 0)', $user->getDeliveryAddress()->getLocation()->getWKT());
        $this->assertSame(4326, $user->getDeliveryAddress()->getLocation()->getSRID());
    }
}
