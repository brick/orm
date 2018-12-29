<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\Db\Connection;
use Brick\Db\Driver\PDO\PDOConnection;
use Brick\Db\Logger\DebugLogger;
use Brick\ORM\Gateway;
use Brick\ORM\Tests\Generated\Repository\CountryRepository;
use Brick\ORM\Tests\Generated\Repository\EventRepository;
use Brick\ORM\Tests\Generated\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    /**
     * @var Gateway
     */
    protected static $gateway;

    /**
     * @var DebugLogger
     */
    protected static $logger;

    /**
     * @var CountryRepository
     */
    protected static $countryRepository;

    /**
     * @var UserRepository
     */
    protected static $userRepository;

    /**
     * @var EventRepository
     */
    protected static $eventRepository;

    /**
     * @todo schema is MySQL only for now, and test server is hardcoded
     *
     * {@inheritdoc}
     */
    public static function setUpBeforeClass() : void
    {
        self::$logger = new DebugLogger();

        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', '');
        $driverConnection = new PDOConnection($pdo);
        $connection = new Connection($driverConnection, self::$logger);
        $classMetadata = require __DIR__ . '/Generated/ClassMetadata.php';

        self::$gateway = new Gateway($connection, $classMetadata);

        self::$countryRepository = new CountryRepository(self::$gateway);
        self::$userRepository = new UserRepository(self::$gateway);
        self::$eventRepository = new EventRepository(self::$gateway);

        $connection->exec('DROP TABLE IF EXISTS Country');
        $connection->exec('
            CREATE TABLE Country (
                code CHAR(2) NOT NULL PRIMARY KEY,
                name VARCHAR(50) NOT NULL
            )
        ');

        $connection->exec('DROP TABLE IF EXISTS User');
        $connection->exec('
            CREATE TABLE User (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                street VARCHAR(50) NULL,
                city VARCHAR(50) NULL,
                zipcode VARCHAR(50) NULL,
                country_code CHAR(2) NULL,
                isPoBox TINYINT(1) NULL,
                deliveryAddress_address_street VARCHAR(50) NULL,
                deliveryAddress_address_city VARCHAR(50) NULL,
                deliveryAddress_address_zipcode VARCHAR(50) NULL,
                deliveryAddress_address_country_code CHAR(2) NULL,
                deliveryAddress_address_isPoBox TINYINT(1) NULL,
                deliveryAddress_location GEOMETRY NULL
            )
        ');

        $connection->exec('DROP TABLE IF EXISTS Event');
        $connection->exec('
            CREATE TABLE Event (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(30) NOT NULL,
                time INT(10) UNSIGNED NOT NULL,
                user_id INT(10) UNSIGNED NULL,
                follower_id INT(10) UNSIGNED NULL,
                followee_id INT(10) UNSIGNED NULL,
                country_code CHAR(2) NULL,
                isFollow TINYINT(1) NULL,
                newName VARCHAR(50) NULL,
                newAddress_street VARCHAR(50) NULL,
                newAddress_city VARCHAR(50) NULL,
                newAddress_zipcode VARCHAR(50) NULL,
                newAddress_country_code CHAR(2) NULL,
                newAddress_isPoBox TINYINT(1) NULL,
                newAddress_address_street VARCHAR(50) NULL,
                newAddress_address_city VARCHAR(50) NULL,
                newAddress_address_zipcode VARCHAR(50) NULL,
                newAddress_address_country_code CHAR(2) NULL,
                newAddress_address_isPoBox TINYINT(1) NULL,
                newAddress_location GEOMETRY NULL
            )
        ');
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp() : void
    {
        self::$logger->reset();
    }

    /**
     * @param int $count
     *
     * @return void
     */
    protected function assertDebugStatementCount(int $count) : void
    {
        self::assertSame($count, self::$logger->count());
    }

    /**
     * @param int    $index
     * @param string $statement
     * @param array  $parameters
     *
     * @return void
     */
    protected function assertDebugStatement(int $index, string $statement, array $parameters) : void
    {
        $debugStatement = self::$logger->getDebugStatement($index);

        self::assertSame($statement, $debugStatement->getStatement());
        self::assertSame($parameters, $debugStatement->getParameters());
        self::assertIsFloat($debugStatement->getTime());
        self::assertGreaterThan(0.0, $debugStatement->getTime());
    }
}
