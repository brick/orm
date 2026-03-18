<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\Options;
use Brick\ORM\Tests\Resources\Models\Address;
use Brick\ORM\Tests\Resources\Models\Country;
use Brick\ORM\Tests\Resources\Models\GeoAddress;
use Brick\ORM\Tests\Resources\Models\User;
use Brick\ORM\Tests\Resources\Objects\Geometry;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;

use function strpos;

class GatewayTest extends AbstractTestCase
{
    public function testAddCountry(): void
    {
        $country = new Country('GB', 'United Kingdom');
        self::$countryRepository->add($country);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'INSERT INTO Country (code, name) VALUES (?, ?)', 'GB', 'United Kingdom');
    }

    #[Depends('testAddCountry')]
    public function testLoadUnknownCountry(): void
    {
        self::assertNull(self::$countryRepository->load('XX'));

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.code, a.name FROM Country AS a WHERE a.code = ?', 'XX');
    }

    #[Depends('testLoadUnknownCountry')]
    public function testLoadCountry(): Country
    {
        $country = self::$countryRepository->load('GB');

        self::assertSame('GB', $country->getCode());
        self::assertSame('United Kingdom', $country->getName());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.code, a.name FROM Country AS a WHERE a.code = ?', 'GB');

        return $country;
    }

    #[Depends('testLoadCountry')]
    public function testAddUser(Country $country): User
    {
        $user = new User('John Smith');

        $billingAddress = new Address('123 Unknown Road', 'London', 'WC2E9XX', $country, false);
        $user->setBillingAddress($billingAddress);

        self::$userRepository->add($user);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(
            0,
            'INSERT INTO User (name, street, city, zipcode, country_code, isPoBox, ' .
            'deliveryAddress_address_street, deliveryAddress_address_city, deliveryAddress_address_zipcode, ' .
            'deliveryAddress_address_country_code, deliveryAddress_address_isPoBox, deliveryAddress_location, ' .
            'lastEvent_type, lastEvent_id, data) ' .
            'VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ?)',
            'John Smith',
            '123 Unknown Road',
            'London',
            'WC2E9XX',
            'GB',
            false,
            '{"any":"data"}',
        );

        // User ID must be set after saving
        self::assertIsInt($user->getId());
        self::assertGreaterThan(0, $user->getId());

        return $user;
    }

    #[Depends('testAddUser')]
    public function testUpdateUser(User $user): int
    {
        $address = $user->getBillingAddress();
        $location = new Geometry('POINT (51 0)', 4326);

        $user->setDeliveryAddress(new GeoAddress($address, $location));
        self::$userRepository->update($user);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(
            0,
            'UPDATE User SET name = ?, street = ?, city = ?, zipcode = ?, country_code = ?, isPoBox = ?, ' .
            'deliveryAddress_address_street = ?, deliveryAddress_address_city = ?, ' .
            'deliveryAddress_address_zipcode = ?, deliveryAddress_address_country_code = ?, ' .
            'deliveryAddress_address_isPoBox = ?, deliveryAddress_location = ST_GeomFromText(?, ?), ' .
            'lastEvent_type = NULL, lastEvent_id = NULL, data = ? ' .
            'WHERE id = ?',
            'John Smith',
            '123 Unknown Road',
            'London',
            'WC2E9XX',
            'GB',
            false,
            '123 Unknown Road',
            'London',
            'WC2E9XX',
            'GB',
            false,
            'POINT (51 0)',
            4326,
            '{"any":"data"}',
            $user->getId(),
        );

        return $user->getId();
    }

    #[Depends('testUpdateUser')]
    public function testLoadPartialUser(int $userId): int
    {
        $user = self::$userRepository->load($userId, 0, 'name');

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.name FROM User AS a WHERE a.id = ?', $userId);

        self::assertSame('John Smith', $user->getName());
        self::assertSame([], $user->getTransient());

        try {
            $user->getBillingAddress();
        } catch (Error $error) {
            if (strpos($error->getMessage(), 'must not be accessed before initialization') !== false) {
                goto ok;
            }
        }

        self::fail('This property should not be set in partial object.');

        ok:

        return $userId;
    }

    #[Depends('testLoadPartialUser')]
    public function testLoadUser(int $userId): User
    {
        $user = self::$userRepository->load($userId);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(
            0,
            'SELECT a.id, a.name, a.street, a.city, a.zipcode, a.country_code, a.isPoBox, ' .
            'a.deliveryAddress_address_street, a.deliveryAddress_address_city, a.deliveryAddress_address_zipcode, ' .
            'a.deliveryAddress_address_country_code, a.deliveryAddress_address_isPoBox, ' .
            'ST_AsText(a.deliveryAddress_location), ST_SRID(a.deliveryAddress_location), ' .
            'a.lastEvent_type, a.lastEvent_id, a.data ' .
            'FROM User AS a WHERE a.id = ?',
            $userId,
        );

        self::assertSame('John Smith', $user->getName());
        self::assertSame('123 Unknown Road', $user->getBillingAddress()->getStreet());
        self::assertSame('London', $user->getBillingAddress()->getCity());
        self::assertSame('WC2E9XX', $user->getBillingAddress()->getPostcode());
        self::assertSame('GB', $user->getBillingAddress()->getCountry()->getCode());
        self::assertSame('123 Unknown Road', $user->getDeliveryAddress()->getAddress()->getStreet());
        self::assertSame('London', $user->getDeliveryAddress()->getAddress()->getCity());
        self::assertSame('WC2E9XX', $user->getDeliveryAddress()->getAddress()->getPostcode());
        self::assertSame('GB', $user->getDeliveryAddress()->getAddress()->getCountry()->getCode());
        self::assertSame('POINT(51 0)', $user->getDeliveryAddress()->getLocation()->getWKT());
        self::assertSame(4326, $user->getDeliveryAddress()->getLocation()->getSRID());
        self::assertSame(['any' => 'data'], $user->getData());

        return $user;
    }

    #[Depends('testLoadUser')]
    public function testRemoveUser(User $user): int
    {
        self::$userRepository->remove($user);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'DELETE FROM User WHERE id = ?', $user->getId());

        return $user->getId();
    }

    #[Depends('testRemoveUser')]
    public function testLoadRemovedUser(int $userId): void
    {
        $user = self::$userRepository->load($userId);
        self::assertNull($user);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(
            0,
            'SELECT a.id, a.name, a.street, a.city, a.zipcode, a.country_code, a.isPoBox, ' .
            'a.deliveryAddress_address_street, a.deliveryAddress_address_city, a.deliveryAddress_address_zipcode, ' .
            'a.deliveryAddress_address_country_code, a.deliveryAddress_address_isPoBox, ' .
            'ST_AsText(a.deliveryAddress_location), ST_SRID(a.deliveryAddress_location), ' .
            'a.lastEvent_type, a.lastEvent_id, a.data ' .
            'FROM User AS a WHERE a.id = ?',
            $userId,
        );
    }

    #[DataProvider('providerLoadWithLock')]
    public function testLoadWithLock(int $options, string $sqlSuffix): void
    {
        self::$countryRepository->load('XX', $options);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.code, a.name FROM Country AS a WHERE a.code = ? ' . $sqlSuffix, 'XX');
    }

    public static function providerLoadWithLock(): array
    {
        return [
            [Options::LOCK_READ, 'FOR SHARE'],
            [Options::LOCK_WRITE, 'FOR UPDATE'],
            [Options::LOCK_READ | Options::SKIP_LOCKED, 'FOR SHARE SKIP LOCKED'],
            [Options::LOCK_WRITE | Options::SKIP_LOCKED, 'FOR UPDATE SKIP LOCKED'],
            [Options::LOCK_READ | Options::NOWAIT, 'FOR SHARE NOWAIT'],
            [Options::LOCK_WRITE | Options::NOWAIT, 'FOR UPDATE NOWAIT'],
        ];
    }

    protected static function useProxies(): bool
    {
        return false;
    }
}
