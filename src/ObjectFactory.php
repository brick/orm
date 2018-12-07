<?php

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
     * Reads object properties.
     *
     * This does not currently aim to support private properties in parent classes.
     *
     * @param object $object The object to read.
     * @param array  $props  A numeric array of property names to read.
     *
     * @return array An associative array mapping property names to values.
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

            $values[$prop] = $reflectionProperty->getValue($object);
        }

        return $values;
    }
}
