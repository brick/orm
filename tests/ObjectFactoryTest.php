<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\ObjectFactory;
use Brick\ORM\Tests\Resources\Models\User;

use PHPUnit\Framework\TestCase;

/**
 * @todo PHP 7.4: test an object with all combinations of properties:
 *
 *  - int $x
 *  - int $x = 1,
 *  - ?int $x
 *  - ?int $x = null
 *  - ?int $x = 1
 *  - $x
 *  - $x = null
 *  - $x = 1
 */
class ObjectFactoryTest extends TestCase
{
    public function testInstantiate()
    {
        $objectFactory = new ObjectFactory();
        $user = $objectFactory->instantiate(User::class, []);

        $this->assertSame(User::class, get_class($user));

        $this->assertSame([
            "\0*\0id" => null,
            "\0*\0name" => null,
            "\0*\0billingAddress" => null,
            "\0*\0deliveryAddress" => null,
            "\0*\0transient" => []
        ], (array) $user);

        $this->assertSame([
            'id' => null,
            'name' => null,
            'billingAddress' => null,
            'deliveryAddress' => null,
            'transient' => []
        ], $objectFactory->read($user));
    }

    public function testInstantiateWithUnsetProps()
    {
        $objectFactory = new ObjectFactory();
        $user = $objectFactory->instantiate(User::class, ['id', 'name', 'billingAddress', 'deliveryAddress']);

        $this->assertSame(User::class, get_class($user));

        $this->assertSame([
            "\0*\0transient" => []
        ], (array) $user);

        $this->assertSame([
            'transient' => []
        ], $objectFactory->read($user));
    }

    public function testWrite()
    {
        $values = [
            'name' => 'John'
        ];

        $objectFactory = new ObjectFactory();
        $user = $objectFactory->instantiate(User::class, ['id', 'name', 'billingAddress', 'deliveryAddress']);
        $objectFactory->write($user, $values);

        $this->assertSame(User::class, get_class($user));

        $this->assertSame([
            "\0*\0name" => 'John',
            "\0*\0transient" => []
        ], (array) $user);

        $this->assertSame([
            'name' => 'John',
            'transient' => []
        ], $objectFactory->read($user));
    }
}
