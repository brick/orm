<?php

declare(strict_types=1);

namespace Brick\ORM;

/**
 * Creates, reads and writes objects.
 *
 * Important note: this does not aim to support private properties in parent classes.
 *
 * This class is performance sensitive, and uses techniques benchmarked here:
 * https://gist.github.com/BenMorel/9a920538862e4df0d7041f8812f069e5
 */
class ObjectFactory
{
    /**
     * An associative array mapping class name to ReflectionClass instances.
     *
     * @var \ReflectionClass[]
     */
    private $classes = [];

    /**
     * Instantiates an empty object, without calling the class constructor and without initializing properties.
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

        $unsetProps = [];

        foreach ($reflectionClass->getDefaultProperties() as $property => $value) {
            $reflectionProperty = $reflectionClass->getProperty($property);

            if ($reflectionProperty->isStatic()) {
                continue;
            }

            $unsetProps[] = $reflectionProperty->getName();
        }

        if ($unsetProps) {
            (function() use ($unsetProps) {
                foreach ($unsetProps as $unsetProp) {
                    unset($this->{$unsetProp});
                }
            })->bindTo($object, $class)();
        }

        if ($values) {
            $this->hydrate($object, $values);
        }

        return $object;
    }

    /**
     * Hydrates an object with an array of values.
     *
     * This method does not support writing private properties in parent classes.
     *
     * @param object $object The object to hydrate.
     * @param array  $values An associative array mapping property names to values.
     *
     * @return void
     */
    public function hydrate(object $object, array $values) : void
    {
        (function() use ($values) {
            foreach ($values as $key => $value) {
                $this->{$key} = $value;
            }
        })->bindTo($object, $object)();
    }

    /**
     * Reads *initialized* object properties.
     *
     * Properties that are not initialized (or have been unset()) are not included in the array.
     * This method assumes that there are no private properties in parent classes.
     *
     * @param object $object The object to read.
     *
     * @return array A map of property names to values.
     */
    public function read(object $object) : array
    {
        $values = [];

        foreach ((array) $object as $key => $value) {
            // Remove the "\0*\0" in front of protected/private properties
            $pos = strrpos($key, "\0");

            if ($pos !== false) {
                $key = substr($key, $pos + 1);
            }

            $values[$key] = $value;
        }

        return $values;
    }
}
