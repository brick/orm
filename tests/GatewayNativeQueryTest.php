<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\Tests\Resources\DTO\UserDTO;

class GatewayNativeQueryTest extends AbstractTestCase
{
    protected static function useProxies() : bool
    {
        return false;
    }

    public function testNativeQuery() : void
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

        $this->assertCount(1, $users);

        $user = $users[0];

        $this->assertSame($userId, $user->id);
        $this->assertSame('Bob', $user->name);
        $this->assertSame('Baker Street', $user->address->street);
        $this->assertSame('London', $user->address->city);
        $this->assertNull($user->address->postcode);
        $this->assertSame('GB', $user->address->countryCode);
        $this->assertSame(3, $user->eventCount);
    }
}
