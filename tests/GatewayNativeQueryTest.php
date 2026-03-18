<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\Tests\Resources\DTO\UserDTO;

class GatewayNativeQueryTest extends AbstractTestCase
{
    public function testNativeQuery(): void
    {
        self::$connection->exec(<<<SQL
            INSERT INTO User (name, street, city, zipcode, country_code, data)
            VALUES ('Bob', 'Baker Street', 'London', NULL, 'GB', '[]')
        SQL);

        $userId = self::$connection->lastInsertId();

        for ($i = 1; $i <= 3; $i++) {
            self::$connection->exec(<<<SQL
                INSERT INTO Event (user_id, type, time)
                VALUES ($userId, 'login', $i)
            SQL);
        }

        /** @var UserDTO[] $users */
        $users = self::$gateway->nativeQuery(UserDTO::class, <<<SQL
            SELECT
                u.id,
                u.name,
                u.street AS address__street,
                u.city AS address__city,
                u.zipcode AS address__postcode, 
                u.country_code AS address__countryCode,
                COUNT(*) AS eventCount
                FROM User u
                LEFT JOIN Event e ON e.user_id = u.id
                GROUP BY u.id
        SQL);

        self::assertCount(1, $users);

        $user = $users[0];

        self::assertSame($userId, $user->id);
        self::assertSame('Bob', $user->name);
        self::assertSame('Baker Street', $user->address->street);
        self::assertSame('London', $user->address->city);
        self::assertNull($user->address->postcode);
        self::assertSame('GB', $user->address->countryCode);
        self::assertSame(3, $user->eventCount);
    }

    protected static function useProxies(): bool
    {
        return false;
    }
}
