<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\LockMode;
use Brick\ORM\Tests\Resources\Models\Address;
use Brick\ORM\Tests\Resources\Models\Country;
use Brick\ORM\Tests\Resources\Models\Event\CountryEvent;
use Brick\ORM\Tests\Resources\Models\Event\CountryEvent\CreateCountryEvent;
use Brick\ORM\Tests\Resources\Models\Event\CountryEvent\EditCountryNameEvent;
use Brick\ORM\Tests\Resources\Models\Event\UserEvent\CreateUserEvent;
use Brick\ORM\Tests\Resources\Models\GeoAddress;
use Brick\ORM\Tests\Resources\Models\User;
use Brick\ORM\Tests\Resources\Objects\Geometry;

class InheritanceTest extends AbstractTestCase
{
    private const LOAD_EVENT_SQL =
        'SELECT type, time, country_code, newName, user_id, ' .
        'newAddress_street, newAddress_city, newAddress_zipcode, newAddress_country_code, newAddress_isPoBox, ' .
        'newAddress_address_street, newAddress_address_city, newAddress_address_zipcode, ' .
        'newAddress_address_country_code, newAddress_address_isPoBox, ST_AsText(newAddress_location), ' .
        'ST_SRID(newAddress_location), newName, follower_id, followee_id, isFollow FROM Event WHERE id = ?';

    /**
     * @return int
     */
    public function testSaveCreateCountryEvent() : int
    {
        $country = new Country('FR', 'France');
        self::$countryRepository->save($country);
        self::$logger->reset();

        $event = new CreateCountryEvent($country);
        self::$eventRepository->save($event);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'INSERT INTO Event (type, country_code, time) VALUES (?, ?, ?)',
            ['CreateCountry', 'FR', 1234567890]
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

        $this->assertSame(CreateCountryEvent::class, get_class($event));

        /** @var CreateCountryEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, self::LOAD_EVENT_SQL, [$eventId]);
    }

    /**
     * @depends testSaveCreateCountryEvent
     *
     * @param int $eventId
     *
     * @return void
     */
    public function testLoadCountryEvent(int $eventId) : void
    {
        $event = self::$gateway->load(CountryEvent::class, ['id' => $eventId], LockMode::NONE, null);

        $this->assertSame(CreateCountryEvent::class, get_class($event));

        /** @var CreateCountryEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT type, country_code, time, newName FROM Event WHERE id = ?', [$eventId]);
    }

    /**
     * @depends testSaveCreateCountryEvent
     *
     * @param int $eventId
     *
     * @return void
     */
    public function testLoadCreateCountryEventUsingClass(int $eventId) : void
    {
        $event = self::$gateway->load(CreateCountryEvent::class, ['id' => $eventId], LockMode::NONE, null);

        $this->assertSame(CreateCountryEvent::class, get_class($event));

        /** @var CreateCountryEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, 'SELECT type, country_code, time FROM Event WHERE id = ?', [$eventId]);
    }

    /**
     * @depends testSaveCreateCountryEvent
     *
     * @param int $eventId
     *
     * @return void
     */
    public function testLoadCreateCountryEventUsingWrongClass(int $eventId) : void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Expected instance of Brick\ORM\Tests\Resources\Models\Event\UserEvent\CreateUserEvent, ' .
            'got Brick\ORM\Tests\Resources\Models\Event\CountryEvent\CreateCountryEvent.'
        );

        self::$gateway->load(CreateUserEvent::class, ['id' => $eventId], LockMode::NONE, null);
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

        $event = new EditCountryNameEvent($country, 'République Française');
        self::$eventRepository->save($event);

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0,
            'INSERT INTO Event (type, newName, country_code, time) VALUES (?, ?, ?, ?)',
            ['EditCountryName', 'République Française', 'FR', 1234567890]
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

        $this->assertSame(EditCountryNameEvent::class, get_class($event));

        /** @var EditCountryNameEvent $event */
        $this->assertSame($eventId, $event->getId());
        $this->assertSame(1234567890, $event->getTime());
        $this->assertSame('République Française', $event->getNewName());
        $this->assertSame('FR', $event->getCountry()->getCode());

        $this->assertDebugStatementCount(1);
        $this->assertDebugStatement(0, self::LOAD_EVENT_SQL, [$eventId]);
    }
}
