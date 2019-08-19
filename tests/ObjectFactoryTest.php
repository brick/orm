<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\EntityMetadata;
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
        $testClassMetadata = new EntityMetadata();
        $testClassMetadata->className = User::class;
        $testClassMetadata->properties = [];

        $objectFactory = new ObjectFactory();
        $user = $objectFactory->instantiate($testClassMetadata);

        $this->assertSame(User::class, get_class($user));

        $this->assertSame([
            "\0*\0billingAddress" => null,
            "\0*\0deliveryAddress" => null,
            "\0*\0lastEvent" => null,
            "\0*\0data" => ['any' => 'data'],
            "\0*\0transient" => []
        ], (array) $user);

        $this->assertSame([
            'billingAddress' => null,
            'deliveryAddress' => null,
            'lastEvent' => null,
            'data' => ['any' => 'data'],
            'transient' => []
        ], $objectFactory->read($user));
    }

    public function testInstantiateWithPersistentProps()
    {
        $testClassMetadata = new EntityMetadata();
        $testClassMetadata->className = User::class;
        $testClassMetadata->properties = ['id', 'name', 'billingAddress', 'deliveryAddress', 'lastEvent', 'data'];

        $objectFactory = new ObjectFactory();
        $user = $objectFactory->instantiate($testClassMetadata);

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

        $testClassMetadata = new EntityMetadata();
        $testClassMetadata->className = User::class;
        $testClassMetadata->properties = ['id', 'name', 'billingAddress', 'deliveryAddress', 'lastEvent', 'data'];

        $objectFactory = new ObjectFactory();
        $user = $objectFactory->instantiate($testClassMetadata);
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
