<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\Exception\UnknownPropertyException;
use Brick\ORM\Query;
use Brick\ORM\Tests\Resources\Models\Country;
use Brick\ORM\Tests\Resources\Models\Event;
use Brick\ORM\Tests\Resources\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;

class InheritanceTest extends AbstractTestCase
{
    /**
     * {@inheritdoc}
     */
    protected static function useProxies() : bool
    {
        return false;
    }

    /**
     * The full SQL to load an Event or one of its subclasses.
     */
    private const LOAD_EVENT_SQL =
        'SELECT a.type, a.id, a.time, a.country_code, a.newName, a.user_id, ' .
        'a.newAddress_street, a.newAddress_city, a.newAddress_zipcode, a.newAddress_country_code, a.newAddress_isPoBox, ' .
        'a.newAddress_address_street, a.newAddress_address_city, a.newAddress_address_zipcode, ' .
        'a.newAddress_address_country_code, a.newAddress_address_isPoBox, ST_AsText(a.newAddress_location), ' .
        'ST_SRID(a.newAddress_location), a.newName, a.follower_id, a.followee_id, a.isFollow FROM Event AS a WHERE a.id = ?';

    /**
     * The full SQL to load a UserEvent or one of its subclasses.
     */
    private const LOAD_USER_EVENT_SQL =
        'SELECT a.type, a.user_id, a.id, a.time, a.newAddress_street, a.newAddress_city, a.newAddress_zipcode, ' .
        'a.newAddress_country_code, a.newAddress_isPoBox, a.newAddress_address_street, a.newAddress_address_city, ' .
        'a.newAddress_address_zipcode, a.newAddress_address_country_code, a.newAddress_address_isPoBox, ' .
        'ST_AsText(a.newAddress_location), ST_SRID(a.newAddress_location), a.newName FROM Event AS a WHERE a.id = ?';

    /**
     * @return int
     */
    public function testAddCreateCountryEvent() : int
    {
        $country = new Country('FR', 'France');
        self::$countryRepository->add($country);
        self::$logger->reset();

        $event = new Event\CountryEvent\CreateCountryEvent($country);
        self::$eventRepository->add($event);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'INSERT INTO Event (type, country_code, time) VALUES (?, ?, ?)',
            'CreateCountry', 'FR', 1234567890
        );

        return $event->getId();
    }

    #[Depends('testAddCreateCountryEvent')]
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
     * @param string $class   The class name to request.
     * @param string $sql     The expected SQL query.
     * @param int    $eventId The ID of the event to load.
     *
     * @return void
     */
    #[Depends('testAddCreateCountryEvent')]
    #[DataProvider('providerLoadCreateCountryEventUsingClass')]
    public function testLoadCreateCountryEventUsingClass(string $class, string $sql, int $eventId) : void
    {
        $event = self::$gateway->load($class, ['id' => $eventId]);

        $this->assertSame(Event\CountryEvent\CreateCountryEvent::class, get_class($event));

        /** @var Event\CountryEvent\CreateCountryEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, $sql, $eventId);
    }

    public static function providerLoadCreateCountryEventUsingClass() : array
    {
        return [
            [Event::class, self::LOAD_EVENT_SQL],
            [Event\CountryEvent::class, 'SELECT a.type, a.country_code, a.id, a.time, a.newName FROM Event AS a WHERE a.id = ?'],
            [Event\CountryEvent\CreateCountryEvent::class, 'SELECT a.type, a.country_code, a.id, a.time FROM Event AS a WHERE a.id = ?'],
        ];
    }

    /**
     * @param string $class   The class name to request.
     * @param int    $eventId The ID of the event to load.
     *
     * @return void
     */
    #[Depends('testAddCreateCountryEvent')]
    #[DataProvider('providerLoadCreateCountryEventUsingWrongClass')]
    public function testLoadCreateCountryEventUsingWrongClass(string $class, int $eventId) : void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('Expected instance of %s, got %s.', $class, Event\CountryEvent\CreateCountryEvent::class));

        self::$gateway->load($class, ['id' => $eventId]);
    }

    public static function providerLoadCreateCountryEventUsingWrongClass() : array
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
     * @param int $eventId The ID of the event to load.
     *
     * @return void
     */
    #[Depends('testAddCreateCountryEvent')]
    public function testLoadPartialCreateCountryEvent(int $eventId) : void
    {
        $event = self::$eventRepository->load($eventId, 0, 'id', 'time');

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.type, a.id, a.time FROM Event AS a WHERE a.id = ?', $eventId);

        $this->assertSame(Event\CountryEvent\CreateCountryEvent::class, get_class($event));

        /** @var Event\CountryEvent\CreateCountryEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());

        try {
            $event->getCountry();
        } catch (\Error $error) {
            if (strpos($error->getMessage(), 'must not be accessed before initialization') !== false) {
                return;
            }
        }

        $this->fail('This property should not be set in partial object.');
    }

    /**
     * @param int $eventId The ID of the event to load.
     */
    #[Depends('testAddCreateCountryEvent')]
    public function testLoadPartialEventUsingPropertyFromChildClass(int $eventId) : void
    {
        $this->expectException(UnknownPropertyException::class);
        $this->expectExceptionMessage('Class "Brick\ORM\Tests\Resources\Models\Event" has no persistent property named "country".');

        self::$eventRepository->load($eventId, 0, 'time', 'country');
    }

    /**
     * @param int $eventId The ID of the event to load.
     */
    #[Depends('testAddCreateCountryEvent')]
    public function testLoadPartialCreateCountryEventUsingClass(int $eventId) : void
    {
        $event = self::$gateway->load(Event\CountryEvent::class, ['id' => $eventId], 0, 'id', 'time', 'country');

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.type, a.id, a.time, a.country_code FROM Event AS a WHERE a.id = ?', $eventId);

        $this->assertSame(Event\CountryEvent\CreateCountryEvent::class, get_class($event));

        /** @var Event\CountryEvent\CreateCountryEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('FR', $event->getCountry()->getCode());
    }

    /**
     * @param int $eventId The ID of the event to load.
     */
    #[Depends('testAddCreateCountryEvent')]
    public function testQueryCreateCountryEvent(int $eventId) : void
    {
        $query = new Query(Event::class);
        $query->addPredicate('id', '=', $eventId);
        $event = self::$gateway->findOne($query);

        $this->assertSame(Event\CountryEvent\CreateCountryEvent::class, get_class($event));

        /** @var Event\CountryEvent\CreateCountryEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $expectedQuery =
            'SELECT a.type, a.id, a.time, a.country_code, a.newName, a.user_id, a.newAddress_street, a.newAddress_city, ' .
            'a.newAddress_zipcode, a.newAddress_country_code, a.newAddress_isPoBox, a.newAddress_address_street, ' .
            'a.newAddress_address_city, a.newAddress_address_zipcode, a.newAddress_address_country_code, ' .
            'a.newAddress_address_isPoBox, ST_AsText(a.newAddress_location), ST_SRID(a.newAddress_location), ' .
            'a.newName, a.follower_id, a.followee_id, a.isFollow FROM Event AS a WHERE a.id = ?';

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, $expectedQuery, $eventId);
    }

    /**
     * @param int $eventId The ID of the event to load.
     */
    #[Depends('testAddCreateCountryEvent')]
    public function testQueryCreateCountryEventWithJoin(int $eventId) : void
    {
        $query = new Query(Event\CountryEvent::class);
        $query->addPredicate('id', '=', $eventId);
        $query->addPredicate('country.name', '=', 'France');
        $event = self::$gateway->findOne($query);

        $this->assertSame(Event\CountryEvent\CreateCountryEvent::class, get_class($event));

        /** @var Event\CountryEvent\CreateCountryEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $expectedQuery =
            'SELECT a.type, a.country_code, a.id, a.time, a.newName FROM Event AS a ' .
            'INNER JOIN Country AS b ON a.country_code = b.code WHERE a.id = ? AND b.name = ?';

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, $expectedQuery, $eventId, 'France');
    }

    #[Depends('testAddCreateCountryEvent')]
    public function testAddEditCountryNameEvent() : int
    {
        $country = self::$countryRepository->load('FR');
        self::$logger->reset();

        $event = new Event\CountryEvent\EditCountryNameEvent($country, 'République Française');
        self::$eventRepository->add($event);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'INSERT INTO Event (type, newName, country_code, time) VALUES (?, ?, ?, ?)',
            'EditCountryName', 'République Française', 'FR', 1234567890
        );

        return $event->getId();
    }

    #[Depends('testAddEditCountryNameEvent')]
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
     * @param string $class   The class name to request.
     * @param string $sql     The expected SQL query.
     * @param int    $eventId The ID of the event to load.
     */
    #[Depends('testAddEditCountryNameEvent')]
    #[DataProvider('providerLoadEditCountryNameEventUsingClass')]
    public function testLoadEditCountryNameEventUsingClass(string $class, string $sql, int $eventId) : void
    {
        $event = self::$gateway->load($class, ['id' => $eventId]);

        $this->assertSame(Event\CountryEvent\EditCountryNameEvent::class, get_class($event));

        /** @var Event\CountryEvent\EditCountryNameEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('République Française', $event->getNewName());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, $sql, $eventId);
    }

    public static function providerLoadEditCountryNameEventUsingClass() : array
    {
        return [
            [Event::class, self::LOAD_EVENT_SQL],
            [Event\CountryEvent::class, 'SELECT a.type, a.country_code, a.id, a.time, a.newName FROM Event AS a WHERE a.id = ?'],
            [Event\CountryEvent\EditCountryNameEvent::class, 'SELECT a.type, a.newName, a.country_code, a.id, a.time FROM Event AS a WHERE a.id = ?'],
        ];
    }

    /**
     * @param string $class   The class name to request.
     * @param int    $eventId The ID of the event to load.
     */
    #[Depends('testAddEditCountryNameEvent')]
    #[DataProvider('providerLoadEditCountryNameEventUsingWrongClass')]
    public function testLoadEditCountryNameEventUsingWrongClass(string $class, int $eventId) : void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('Expected instance of %s, got %s.', $class, Event\CountryEvent\EditCountryNameEvent::class));

        self::$gateway->load($class, ['id' => $eventId]);
    }

    public static function providerLoadEditCountryNameEventUsingWrongClass() : array
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
     * @param int $eventId The ID of the event to load.
     */
    #[Depends('testAddEditCountryNameEvent')]
    public function testLoadPartialEditCountryNameEventUsingClass(int $eventId) : void
    {
        $event = self::$gateway->load(Event\CountryEvent::class, ['id' => $eventId], 0, 'id', 'time', 'country');

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT a.type, a.id, a.time, a.country_code FROM Event AS a WHERE a.id = ?', $eventId);

        $this->assertSame(Event\CountryEvent\EditCountryNameEvent::class, get_class($event));

        /** @var Event\CountryEvent\EditCountryNameEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('FR', $event->getCountry()->getCode());

        try {
            $event->getNewName();
        } catch (\Error $error) {
            if (strpos($error->getMessage(), 'must not be accessed before initialization') !== false) {
                return;
            }
        }

        $this->fail('This property should not be set in partial object.');
    }

    /**
     * @return int[]
     */
    public function testAddCreateUserEvent() : array
    {
        $user = new User('John');
        self::$userRepository->add($user);
        self::$logger->reset();

        $event = new Event\UserEvent\CreateUserEvent($user);
        self::$eventRepository->add($event);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'INSERT INTO Event (type, user_id, time) VALUES (?, ?, ?)',
            'CreateUser', $user->getId(), 1234567890
        );

        return [$user->getId(), $event->getId()];
    }

    /**
     * @param int[] $ids The user and event IDs.
     */
    #[Depends('testAddCreateUserEvent')]
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
     * @param string $class The class name to request.
     * @param string $sql   The expected SQL query.
     * @param int[]  $ids   The User and Event IDs.
     *
     * @return void
     */
    #[Depends('testAddCreateUserEvent')]
    #[DataProvider('providerLoadCreateUserEventUsingClass')]
    public function testLoadCreateUserEventUsingClass(string $class, string $sql, array $ids) : void
    {
        [$userId, $eventId] = $ids;

        $event = self::$gateway->load($class, ['id' => $eventId]);

        $this->assertSame(Event\UserEvent\CreateUserEvent::class, get_class($event));

        /** @var Event\UserEvent\CreateUserEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame($userId, $event->getUser()->getId());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, $sql, $eventId);
    }

    public static function providerLoadCreateUserEventUsingClass() : array
    {
        return [
            [Event::class, self::LOAD_EVENT_SQL],
            [Event\UserEvent::class, self::LOAD_USER_EVENT_SQL],
            [Event\UserEvent\CreateUserEvent::class, 'SELECT a.type, a.user_id, a.id, a.time FROM Event AS a WHERE a.id = ?'],
        ];
    }

    /**
     * @param string $class The class name to request.
     * @param int[]  $ids   The User and Event IDs.
     *
     * @return void
     */
    #[Depends('testAddCreateUserEvent')]
    #[DataProvider('providerLoadCreateUserEventUsingWrongClass')]
    public function testLoadCreateUserEventUsingWrongClass(string $class, array $ids) : void
    {
        [$userId, $eventId] = $ids;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('Expected instance of %s, got %s.', $class, Event\UserEvent\CreateUserEvent::class));

        self::$gateway->load($class, ['id' => $eventId]);
    }

    public static function providerLoadCreateUserEventUsingWrongClass() : array
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
     * @param int[] $ids The User and (unused here) Event IDs.
     *
     * @return int[]
     */
    #[Depends('testAddCreateUserEvent')]
    public function testAddEditUserNameEvent(array $ids) : array
    {
        [$userId] = $ids;

        $user = self::$userRepository->load($userId);
        self::$logger->reset();

        $event = new Event\UserEvent\EditUserNameEvent($user, 'Ben');
        self::$eventRepository->add($event);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'INSERT INTO Event (type, newName, user_id, time) VALUES (?, ?, ?, ?)',
            'EditUserName', 'Ben', $userId, 1234567890
        );

        return [$userId, $event->getId()];
    }

    /**
     * @param int[] $ids The User and Event IDs.
     *
     * @return void
     */
    #[Depends('testAddEditUserNameEvent')]
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
     * @param string $class The class name to request.
     * @param string $sql   The expected SQL query.
     * @param int[]  $ids   The User and Event IDs.
     *
     * @return void
     */
    #[Depends('testAddEditUserNameEvent')]
    #[DataProvider('providerLoadEditUserNameEventUsingClass')]
    public function testLoadEditUserNameEventUsingClass(string $class, string $sql, array $ids) : void
    {
        [$userId, $eventId] = $ids;

        $event = self::$gateway->load($class, ['id' => $eventId]);

        $this->assertSame(Event\UserEvent\EditUserNameEvent::class, get_class($event));

        /** @var Event\UserEvent\EditUserNameEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('Ben', $event->getNewName());
        $this->assertSame($userId, $event->getUser()->getId());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, $sql, $eventId);
    }

    public static function providerLoadEditUserNameEventUsingClass() : array
    {
        return [
            [Event::class, self::LOAD_EVENT_SQL],
            [Event\UserEvent::class, self::LOAD_USER_EVENT_SQL],
            [Event\UserEvent\EditUserNameEvent::class, 'SELECT a.type, a.newName, a.user_id, a.id, a.time FROM Event AS a WHERE a.id = ?'],
        ];
    }

    /**
     * @param string $class The class name to request.
     * @param int[]  $ids   The User and Event IDs.
     *
     * @return void
     */
    #[Depends('testAddEditUserNameEvent')]
    #[DataProvider('providerLoadEditUserNameEventUsingWrongClass')]
    public function testLoadEditUserNameEventUsingWrongClass(string $class, array $ids) : void
    {
        [$userId, $eventId] = $ids;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('Expected instance of %s, got %s.', $class, Event\UserEvent\EditUserNameEvent::class));

        self::$gateway->load($class, ['id' => $eventId]);
    }

    public static function providerLoadEditUserNameEventUsingWrongClass() : array
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
