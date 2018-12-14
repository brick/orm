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
        $user = $objectFactory->instantiate(User::class);

        $this->assertSame(User::class, get_class($user));
        $this->assertSame([], $this->getInitializedProperties($user));
    }

    public function testInstantiateWithValues()
    {
        $values = [
            'name' => 'Ben',
            'status' => 'pending'
        ];

        $objectFactory = new ObjectFactory();
        $user = $objectFactory->instantiate(User::class, $values);

        $this->assertSame(User::class, get_class($user));
        $this->assertSame($values, $this->getInitializedProperties($user));
    }

    /**
     * @param object $object
     *
     * @return string[]
     */
    private function getInitializedProperties(object $object) : array
    {
        $properties = [];

        // Remove the "\0*\0" in front of protected properties.

        foreach ((array) $object as $key => $value) {
            $pos = strrpos($key, "\0");

            if ($pos !== false) {
                $key = substr($key, $pos + 1);
            }

            $properties[$key] = $value;
        }

        return $properties;
    }
}
