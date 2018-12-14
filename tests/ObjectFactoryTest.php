<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\ObjectFactory;
use Brick\ORM\Tests\TestEntities\User;

use PHPUnit\Framework\TestCase;

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
            "\0*\0status" => 'active',
            "\0*\0reputation" => 0,
            "\0*\0transient" => []
        ], (array) $user);

        $this->assertSame([
            'id' => null,
            'name' => null,
            'status' => 'active',
            'reputation' => 0,
            'transient' => []
        ], $objectFactory->read($user));
    }

    public function testInstantiateWithUnsetProps()
    {
        $objectFactory = new ObjectFactory();
        $user = $objectFactory->instantiate(User::class, ['id', 'name', 'status', 'reputation']);

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
            'name' => 'Ben',
            'status' => 'pending'
        ];

        $objectFactory = new ObjectFactory();
        $user = $objectFactory->instantiate(User::class, ['id', 'name', 'status', 'reputation']);
        $objectFactory->write($user, $values);

        $this->assertSame(User::class, get_class($user));

        $this->assertSame([
            "\0*\0name" => 'Ben',
            "\0*\0status" => 'pending',
            "\0*\0transient" => []
        ], (array) $user);

        $this->assertSame([
            'name' => 'Ben',
            'status' => 'pending',
            'transient' => []
        ], $objectFactory->read($user));
    }
}
