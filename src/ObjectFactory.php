<?php

declare(strict_types=1);

namespace Brick\ORM;

class ObjectFactory
{
    /**
     * An associative array mapping class name to ReflectionClass instances.
     *
     * @var \ReflectionClass[]
     */
    private $classes = [];

    /**
     * An associative array mapping class name to property name to ReflectionProperty instances.
     *
     * @var \ReflectionProperty[][]
     */
    private $properties = [];

    /**
     * Instantiates an object without calling the class constructor.
     *
     * @param string $class
     * @param array  $values
     *
     * @return object
     *
     * @throws \ReflectionException If the class does not exist.
     */
    public function instantiate(string $class, array $values = []) : object
    {
        if (isset($this->classes[$class])) {
            $reflectionClass = $this->classes[$class];
        } else {
            $reflectionClass = $this->classes[$class] = new \ReflectionClass($class);
        }

        $object = $reflectionClass->newInstanceWithoutConstructor();

        // Unset (actually, set null for now) properties that are not in $values and have a default value
        // See: https://externals.io/message/103601
        // @todo update for PHP 7.4

        foreach ($reflectionClass->getDefaultProperties() as $property => $value) {
            $reflectionProperty = $reflectionClass->getProperty($property);

            if ($reflectionProperty->isStatic()) {
                continue;
            }

            $propertyName = $reflectionProperty->getName();

            if (! isset($values[$propertyName])) {
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($object, null);
            }
        }

        if ($values) {
            $this->hydrate($object, $values);
        }

        return $object;
    }

    /**
     * Hydrates an object with an array of values.
     *
     * This does not currently aim to support private properties in parent classes.
     *
     * @param object $object The object to hydrate.
     * @param array  $values An associative array mapping property names to values.
     *
     * @return void
     *
     * @throws \ReflectionException If a property does not exist.
     */
    public function hydrate(object $object, array $values) : void
    {
        $class = get_class($object);

        if (isset($this->classes[$class])) {
            $reflectionClass = $this->classes[$class];
        } else {
            $reflectionClass = $this->classes[$class] = new \ReflectionClass($class);
        }

        foreach ($values as $property => $value) {
            if (isset($this->properties[$class][$property])) {
                $reflectionProperty = $this->properties[$class][$property];
            } else {
                $reflectionProperty = $this->properties[$class][$property] = $reflectionClass->getProperty($property);
                $reflectionProperty->setAccessible(true);
            }

            $reflectionProperty->setValue($object, $value);
        }
    }

    /**
     * Reads *initialized* object properties.
     *
     * Only initialized properties are read from the object; in PHP 7.4 this will take on its full meaning,
     * in the meantime we always consider null to be uninitialized; there is not ambiguity for non-nullable
     * properties, but for nullable properties we cannot make the difference between a null property and an
     * uninitialized property, so null will still be considered uninitialized.
     *
     * The impact will be visible when attempting to update() an existing entity, and a nullable property has been
     * explicitly set to null: this property will *not* be saved to the database. This will be fixed with PHP 7.4.
     *
     * @todo Change for PHP 7.4
     *
     * This does not currently aim to support private properties in parent classes.
     *
     * @param object $object The object to read.
     * @param array  $props  A numeric array of property names to read.
     *
     * @return array A map of property names to values.
     *
     * @throws \ReflectionException If a property does not exist.
     */
    public function read(object $object, array $props) : array
    {
        $class = get_class($object);

        if (isset($this->classes[$class])) {
            $reflectionClass = $this->classes[$class];
        } else {
            $reflectionClass = $this->classes[$class] = new \ReflectionClass($class);
        }

        $values = [];

        foreach ($props as $prop) {
            if (isset($this->properties[$class][$prop])) {
                $reflectionProperty = $this->properties[$class][$prop];
            } else {
                $reflectionProperty = $this->properties[$class][$prop] = $reflectionClass->getProperty($prop);
                $reflectionProperty->setAccessible(true);
            }

            // @todo if ($reflectionProperty->isInitialized()) {
            $value = $reflectionProperty->getValue($object);

            if ($value !== null) {
                $values[$prop] = $value;
            }
        }

        return $values;
    }
}
