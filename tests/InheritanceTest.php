<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\LockMode;
use Brick\ORM\Tests\Resources\Models\Country;
use Brick\ORM\Tests\Resources\Models\Event;
use Brick\ORM\Tests\Resources\Models\User;

class InheritanceTest extends AbstractTestCase
{
    /**
     * The full SQL to load an Event or one of its subclasses.
     */
    private const LOAD_EVENT_SQL =
        'SELECT type, time, country_code, newName, user_id, ' .
        'newAddress_street, newAddress_city, newAddress_zipcode, newAddress_country_code, newAddress_isPoBox, ' .
        'newAddress_address_street, newAddress_address_city, newAddress_address_zipcode, ' .
        'newAddress_address_country_code, newAddress_address_isPoBox, ST_AsText(newAddress_location), ' .
        'ST_SRID(newAddress_location), newName, follower_id, followee_id, isFollow FROM Event WHERE id = ?';

    /**
     * The full SQL to load a UserEvent or one of its subclasses.
     */
    private const LOAD_USER_EVENT_SQL =
        'SELECT type, user_id, time, newAddress_street, newAddress_city, newAddress_zipcode, ' .
        'newAddress_country_code, newAddress_isPoBox, newAddress_address_street, newAddress_address_city, ' .
        'newAddress_address_zipcode, newAddress_address_country_code, newAddress_address_isPoBox, ' .
        'ST_AsText(newAddress_location), ST_SRID(newAddress_location), newName FROM Event WHERE id = ?';

    /**
     * @return int
     */
    public function testSaveCreateCountryEvent() : int
    {
        $country = new Country('FR', 'France');
        self::$countryRepository->save($country);
        self::$logger->reset();

        $event = new Event\CountryEvent\CreateCountryEvent($country);
        self::$eventRepository->save($event);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'INSERT INTO Event (type, country_code, time) VALUES (?, ?, ?)',
            'CreateCountry', 'FR', 1234567890
        );

        return $event->getId();
    }

    /**
     * @depends testSaveCreateCountryEvent
     *
     * @param int $eventId
     *
     * @return void
     */
    public function testLoadCreateCountryEvent(int $eventId) : void
    {
        $event = self::$eventRepository->load($eventId);

        $this->assertSame(Event\CountryEvent\CreateCountryEvent::class, get_class($event));

        /** @var Event\CountryEvent\CreateCountryEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, self::LOAD_EVENT_SQL, $eventId);
    }

    /**
     * @depends testSaveCreateCountryEvent
     * @dataProvider providerLoadCreateCountryEventUsingClass
     *
     * @param string $class   The class name to request.
     * @param string $sql     The expected SQL query.
     * @param int    $eventId The ID of the event to load.
     *
     * @return void
     */
    public function testLoadCreateCountryEventUsingClass(string $class, string $sql, int $eventId) : void
    {
        $event = self::$gateway->load($class, ['id' => $eventId], LockMode::NONE, null);

        $this->assertSame(Event\CountryEvent\CreateCountryEvent::class, get_class($event));

        /** @var Event\CountryEvent\CreateCountryEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, $sql, $eventId);
    }

    /**
     * @return array
     */
    public function providerLoadCreateCountryEventUsingClass() : array
    {
        return [
            [Event::class, self::LOAD_EVENT_SQL],
            [Event\CountryEvent::class, 'SELECT type, country_code, time, newName FROM Event WHERE id = ?'],
            [Event\CountryEvent\CreateCountryEvent::class, 'SELECT type, country_code, time FROM Event WHERE id = ?'],
        ];
    }

    /**
     * @depends testSaveCreateCountryEvent
     * @dataProvider providerLoadCreateCountryEventUsingWrongClass
     *
     * @param string $class   The class name to request.
     * @param int    $eventId The ID of the event to load.
     *
     * @return void
     */
    public function testLoadCreateCountryEventUsingWrongClass(string $class, int $eventId) : void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('Expected instance of %s, got %s.', $class, Event\CountryEvent\CreateCountryEvent::class));

        self::$gateway->load($class, ['id' => $eventId], LockMode::NONE, null);
    }

    /**
     * @return array
     */
    public function providerLoadCreateCountryEventUsingWrongClass() : array
    {
        return [
            [Event\CountryEvent\EditCountryNameEvent::class],
            [Event\UserEvent::class],
            [Event\UserEvent\CreateUserEvent::class],
            [Event\UserEvent\EditUserBillingAddressEvent::class],
            [Event\UserEvent\EditUserDeliveryAddressEvent::class],
            [Event\UserEvent\EditUserNameEvent::class],
            [Event\FollowUserEvent::class],
        ];
    }

    /**
     * @depends testSaveCreateCountryEvent
     *
     * @return int
     */
    public function testSaveEditCountryNameEvent() : int
    {
        $country = self::$countryRepository->load('FR');
        self::$logger->reset();

        $event = new Event\CountryEvent\EditCountryNameEvent($country, 'République Française');
        self::$eventRepository->save($event);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'INSERT INTO Event (type, newName, country_code, time) VALUES (?, ?, ?, ?)',
            'EditCountryName', 'République Française', 'FR', 1234567890
        );

        return $event->getId();
    }

    /**
     * @depends testSaveEditCountryNameEvent
     *
     * @param int $eventId
     *
     * @return void
     */
    public function testLoadEditCountryNameEvent(int $eventId) : void
    {
        $event = self::$eventRepository->load($eventId);

        $this->assertSame(Event\CountryEvent\EditCountryNameEvent::class, get_class($event));

        /** @var Event\CountryEvent\EditCountryNameEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('République Française', $event->getNewName());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, self::LOAD_EVENT_SQL, $eventId);
    }

    /**
     * @depends testSaveEditCountryNameEvent
     * @dataProvider providerLoadEditCountryNameEventUsingClass
     *
     * @param string $class   The class name to request.
     * @param string $sql     The expected SQL query.
     * @param int    $eventId The ID of the event to load.
     *
     * @return void
     */
    public function testLoadEditCountryNameEventUsingClass(string $class, string $sql, int $eventId) : void
    {
        $event = self::$gateway->load($class, ['id' => $eventId], LockMode::NONE, null);

        $this->assertSame(Event\CountryEvent\EditCountryNameEvent::class, get_class($event));

        /** @var Event\CountryEvent\EditCountryNameEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('République Française', $event->getNewName());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, $sql, $eventId);
    }

    /**
     * @return array
     */
    public function providerLoadEditCountryNameEventUsingClass() : array
    {
        return [
            [Event::class, self::LOAD_EVENT_SQL],
            [Event\CountryEvent::class, 'SELECT type, country_code, time, newName FROM Event WHERE id = ?'],
            [Event\CountryEvent\EditCountryNameEvent::class, 'SELECT type, newName, country_code, time FROM Event WHERE id = ?'],
        ];
    }

    /**
     * @depends testSaveEditCountryNameEvent
     * @dataProvider providerLoadEditCountryNameEventUsingWrongClass
     *
     * @param string $class   The class name to request.
     * @param int    $eventId The ID of the event to load.
     *
     * @return void
     */
    public function testLoadEditCountryNameEventUsingWrongClass(string $class, int $eventId) : void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('Expected instance of %s, got %s.', $class, Event\CountryEvent\EditCountryNameEvent::class));

        self::$gateway->load($class, ['id' => $eventId], LockMode::NONE, null);
    }

    /**
     * @return array
     */
    public function providerLoadEditCountryNameEventUsingWrongClass() : array
    {
        return [
            [Event\CountryEvent\CreateCountryEvent::class],
            [Event\UserEvent::class],
            [Event\UserEvent\CreateUserEvent::class],
            [Event\UserEvent\EditUserBillingAddressEvent::class],
            [Event\UserEvent\EditUserDeliveryAddressEvent::class],
            [Event\UserEvent\EditUserNameEvent::class],
            [Event\FollowUserEvent::class],
        ];
    }

    /**
     * @return int[]
     */
    public function testSaveCreateUserEvent() : array
    {
        $user = new User('John');
        self::$userRepository->save($user);
        self::$logger->reset();

        $event = new Event\UserEvent\CreateUserEvent($user);
        self::$eventRepository->save($event);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'INSERT INTO Event (type, user_id, time) VALUES (?, ?, ?)',
            'CreateUser', $user->getId(), 1234567890
        );

        return [$user->getId(), $event->getId()];
    }

    /**
     * @depends testSaveCreateUserEvent
     *
     * @param int[] $ids The user and event IDs.
     *
     * @return void
     */
    public function testLoadCreateUserEvent(array $ids) : void
    {
        [$userId, $eventId] = $ids;

        $event = self::$eventRepository->load($eventId);

        $this->assertSame(Event\UserEvent\CreateUserEvent::class, get_class($event));

        /** @var Event\UserEvent\CreateUserEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame($userId, $event->getUser()->getId());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, self::LOAD_EVENT_SQL, $eventId);
    }

    /**
     * @depends testSaveCreateUserEvent
     * @dataProvider providerLoadCreateUserEventUsingClass
     *
     * @param string $class The class name to request.
     * @param string $sql   The expected SQL query.
     * @param int[]  $ids   The User and Event IDs.
     *
     * @return void
     */
    public function testLoadCreateUserEventUsingClass(string $class, string $sql, array $ids) : void
    {
        [$userId, $eventId] = $ids;

        $event = self::$gateway->load($class, ['id' => $eventId], LockMode::NONE, null);

        $this->assertSame(Event\UserEvent\CreateUserEvent::class, get_class($event));

        /** @var Event\UserEvent\CreateUserEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame($userId, $event->getUser()->getId());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, $sql, $eventId);
    }

    /**
     * @return array
     */
    public function providerLoadCreateUserEventUsingClass() : array
    {
        return [
            [Event::class, self::LOAD_EVENT_SQL],
            [Event\UserEvent::class, self::LOAD_USER_EVENT_SQL],
            [Event\UserEvent\CreateUserEvent::class, 'SELECT type, user_id, time FROM Event WHERE id = ?'],
        ];
    }

    /**
     * @depends testSaveCreateUserEvent
     * @dataProvider providerLoadCreateUserEventUsingWrongClass
     *
     * @param string $class The class name to request.
     * @param int[]  $ids   The User and Event IDs.
     *
     * @return void
     */
    public function testLoadCreateUserEventUsingWrongClass(string $class, array $ids) : void
    {
        [$userId, $eventId] = $ids;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('Expected instance of %s, got %s.', $class, Event\UserEvent\CreateUserEvent::class));

        self::$gateway->load($class, ['id' => $eventId], LockMode::NONE, null);
    }

    /**
     * @return array
     */
    public function providerLoadCreateUserEventUsingWrongClass() : array
    {
        return [
            [Event\CountryEvent::class],
            [Event\CountryEvent\CreateCountryEvent::class],
            [Event\CountryEvent\EditCountryNameEvent::class],
            [Event\UserEvent\EditUserNameEvent::class],
            [Event\UserEvent\EditUserBillingAddressEvent::class],
            [Event\UserEvent\EditUserDeliveryAddressEvent::class],
            [Event\FollowUserEvent::class],
        ];
    }

    /**
     * @depends testSaveCreateUserEvent
     *
     * @param int[] $ids The User and (unused here) Event IDs.
     *
     * @return int[]
     */
    public function testSaveEditUserNameEvent(array $ids) : array
    {
        [$userId] = $ids;

        $user = self::$userRepository->load($userId);
        self::$logger->reset();

        $event = new Event\UserEvent\EditUserNameEvent($user, 'Ben');
        self::$eventRepository->save($event);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'INSERT INTO Event (type, newName, user_id, time) VALUES (?, ?, ?, ?)',
            'EditUserName', 'Ben', $userId, 1234567890
        );

        return [$userId, $event->getId()];
    }

    /**
     * @depends testSaveEditUserNameEvent
     *
     * @param int[] $ids The User and Event IDs.
     *
     * @return void
     */
    public function testLoadEditUserNameEvent(array $ids) : void
    {
        [$userId, $eventId] = $ids;

        $event = self::$eventRepository->load($eventId);

        $this->assertSame(Event\UserEvent\EditUserNameEvent::class, get_class($event));

        /** @var Event\UserEvent\EditUserNameEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('Ben', $event->getNewName());
        $this->assertSame($userId, $event->getUser()->getId());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, self::LOAD_EVENT_SQL, $eventId);
    }

    /**
     * @depends testSaveEditUserNameEvent
     * @dataProvider providerLoadEditUserNameEventUsingClass
     *
     * @param string $class The class name to request.
     * @param string $sql   The expected SQL query.
     * @param int[]  $ids   The User and Event IDs.
     *
     * @return void
     */
    public function testLoadEditUserNameEventUsingClass(string $class, string $sql, array $ids) : void
    {
        [$userId, $eventId] = $ids;

        $event = self::$gateway->load($class, ['id' => $eventId], LockMode::NONE, null);

        $this->assertSame(Event\UserEvent\EditUserNameEvent::class, get_class($event));

        /** @var Event\UserEvent\EditUserNameEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('Ben', $event->getNewName());
        $this->assertSame($userId, $event->getUser()->getId());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, $sql, $eventId);
    }

    /**
     * @return array
     */
    public function providerLoadEditUserNameEventUsingClass() : array
    {
        return [
            [Event::class, self::LOAD_EVENT_SQL],
            [Event\UserEvent::class, self::LOAD_USER_EVENT_SQL],
            [Event\UserEvent\EditUserNameEvent::class, 'SELECT type, newName, user_id, time FROM Event WHERE id = ?'],
        ];
    }

    /**
     * @depends testSaveEditUserNameEvent
     * @dataProvider providerLoadEditUserNameEventUsingWrongClass
     *
     * @param string $class The class name to request.
     * @param int[]  $ids   The User and Event IDs.
     *
     * @return void
     */
    public function testLoadEditUserNameEventUsingWrongClass(string $class, array $ids) : void
    {
        [$userId, $eventId] = $ids;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('Expected instance of %s, got %s.', $class, Event\UserEvent\EditUserNameEvent::class));

        self::$gateway->load($class, ['id' => $eventId], LockMode::NONE, null);
    }

    /**
     * @return array
     */
    public function providerLoadEditUserNameEventUsingWrongClass() : array
    {
        return [
            [Event\CountryEvent::class],
            [Event\CountryEvent\CreateCountryEvent::class],
            [Event\CountryEvent\EditCountryNameEvent::class],
            [Event\UserEvent\CreateUserEvent::class],
            [Event\UserEvent\EditUserBillingAddressEvent::class],
            [Event\UserEvent\EditUserDeliveryAddressEvent::class],
            [Event\FollowUserEvent::class],
        ];
    }
}
