<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\Options;
use Brick\ORM\Tests\Resources\Models\Address;
use Brick\ORM\Tests\Resources\Models\Country;
use Brick\ORM\Tests\Resources\Models\GeoAddress;
use Brick\ORM\Tests\Resources\Models\User;
use Brick\ORM\Tests\Resources\Objects\Geometry;

class GatewayTest extends AbstractTestCase
{
    /**
     * {@inheritdoc}
     */
    protected static function useProxies() : bool
    {
        return false;
    }

    public function testSaveCountry() : void
    {
        $country = new Country('GB', 'United Kingdom');
        self::$countryRepository->save($country);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'INSERT INTO Country (code, name) VALUES (?, ?)', 'GB', 'United Kingdom');
    }

    /**
     * @depends testSaveCountry
     */
    public function testLoadUnknownCountry() : void
    {
        $this->assertNull(self::$countryRepository->load('XX'));

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.code, a.name FROM Country AS a WHERE a.code = ?', 'XX');
    }

    /**
     * @depends testLoadUnknownCountry
     *
     * @return Country
     */
    public function testLoadCountry() : Country
    {
        $country = self::$countryRepository->load('GB');

        $this->assertSame('GB', $country->getCode());
        $this->assertSame('United Kingdom', $country->getName());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.code, a.name FROM Country AS a WHERE a.code = ?', 'GB');

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
            'INSERT INTO User (name, street, city, zipcode, country_code, isPoBox, ' .
            'deliveryAddress_address_street, deliveryAddress_address_city, deliveryAddress_address_zipcode, ' .
            'deliveryAddress_address_country_code, deliveryAddress_address_isPoBox, deliveryAddress_location, ' .
            'lastEvent_type, lastEvent_id, data) ' .
            'VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ?)',
            'John Smith', '123 Unknown Road', 'London', 'WC2E9XX', 'GB', false, '{"any":"data"}'
        );

        // User ID must be set after saving
        $this->assertIsInt($user->getId());
        $this->assertGreaterThan(0, $user->getId());

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
            'deliveryAddress_address_isPoBox = ?, deliveryAddress_location = ST_GeomFromText(?, ?), ' .
            'lastEvent_type = NULL, lastEvent_id = NULL, data = ? ' .
            'WHERE id = ?',
            'John Smith',
            '123 Unknown Road', 'London', 'WC2E9XX', 'GB', false,
            '123 Unknown Road', 'London', 'WC2E9XX', 'GB', false,
            'POINT (51 0)', 4326, '{"any":"data"}', $user->getId()
        );

        return $user->getId();
    }

    /**
     * @depends testUpdateUser
     *
     * @param int $userId
     *
     * @return int
     */
    public function testLoadPartialUser(int $userId) : int
    {
        $user = self::$userRepository->load($userId, 0, 'name');

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.name FROM User AS a WHERE a.id = ?', $userId);

        $this->assertSame('John Smith', $user->getName());
        $this->assertSame([], $user->getTransient());

        try {
            $user->getBillingAddress();
        } catch (\Error $error) {
            if (strpos($error->getMessage(), 'must not be accessed before initialization') !== false) {
                goto ok;
            }
        }

        $this->fail('This property should not be set in partial object.');

        ok:

        return $userId;
    }

    /**
     * @depends testLoadPartialUser
     *
     * @param int $userId
     *
     * @return User
     */
    public function testLoadUser(int $userId) : User
    {
        $user = self::$userRepository->load($userId);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'SELECT a.id, a.name, a.street, a.city, a.zipcode, a.country_code, a.isPoBox, ' .
            'a.deliveryAddress_address_street, a.deliveryAddress_address_city, a.deliveryAddress_address_zipcode, ' .
            'a.deliveryAddress_address_country_code, a.deliveryAddress_address_isPoBox, ' .
            'ST_AsText(a.deliveryAddress_location), ST_SRID(a.deliveryAddress_location), ' .
            'a.lastEvent_type, a.lastEvent_id, a.data ' .
            'FROM User AS a WHERE a.id = ?',
            $userId
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
        $this->assertSame(['any' => 'data'], $user->getData());

        return $user;
    }

    /**
     * @depends testLoadUser
     *
     * @param User $user
     *
     * @return int
     */
    public function testRemoveUser(User $user) : int
    {
        self::$userRepository->remove($user);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'DELETE FROM User WHERE id = ?', $user->getId());

        return $user->getId();
    }

    /**
     * @depends testRemoveUser
     *
     * @param int $userId
     *
     * @return void
     */
    public function testLoadRemovedUser(int $userId) : void
    {
        $user = self::$userRepository->load($userId);
        $this->assertNull($user);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'SELECT a.id, a.name, a.street, a.city, a.zipcode, a.country_code, a.isPoBox, ' .
            'a.deliveryAddress_address_street, a.deliveryAddress_address_city, a.deliveryAddress_address_zipcode, ' .
            'a.deliveryAddress_address_country_code, a.deliveryAddress_address_isPoBox, ' .
            'ST_AsText(a.deliveryAddress_location), ST_SRID(a.deliveryAddress_location), ' .
            'a.lastEvent_type, a.lastEvent_id, a.data ' .
            'FROM User AS a WHERE a.id = ?',
            $userId
        );
    }

    /**
     * @dataProvider providerLoadWithLock
     *
     * @param int    $options
     * @param string $sqlSuffix
     *
     * @return void
     */
    public function testLoadWithLock(int $options, string $sqlSuffix) : void
    {
        self::$countryRepository->load('XX', $options);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.code, a.name FROM Country AS a WHERE a.code = ? ' . $sqlSuffix, 'XX');
    }

    /**
     * @return array
     */
    public function providerLoadWithLock() : array
    {
        return [
            [Options::LOCK_READ, 'FOR SHARE'],
            [Options::LOCK_WRITE, 'FOR UPDATE'],
            [Options::LOCK_READ | Options::SKIP_LOCKED, 'FOR SHARE SKIP LOCKED'],
            [Options::LOCK_WRITE | Options::SKIP_LOCKED, 'FOR UPDATE SKIP LOCKED'],
        ];
    }
}
