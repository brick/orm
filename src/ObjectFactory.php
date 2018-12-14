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
     * A map of fully qualified class names to ReflectionClass instances.
     *
     * @var \ReflectionClass[]
     */
    private $classes = [];

    /**
     * Instantiates an empty object, without calling the class constructor.
     *
     * This aim of this method is to return an object whose *persistent* properties are not initialized.
     *
     * It must be called with the list of properties to unset: we cannot blindly unset() every single property in the
     * class, as we should not unset() transient properties, if any; transient properties should keep their default
     * value if they have one.
     *
     * This method is therefore aimed to be called with the list of persistent properties of the object. This list
     * should ideally be pre-filtered to remove typed properties that have no default value, as unset() will have no
     * effect on them, for even better performance.
     *
     * @param string   $class      The fully-qualified class name.
     * @param string[] $unsetProps The list of properties to unset.
     *
     * @return object
     *
     * @throws \ReflectionException If the class does not exist.
     */
    public function instantiate(string $class, array $unsetProps) : object
    {
        if (isset($this->classes[$class])) {
            $reflectionClass = $this->classes[$class];
        } else {
            $reflectionClass = $this->classes[$class] = new \ReflectionClass($class);
        }

        $object = $reflectionClass->newInstanceWithoutConstructor();

        if ($unsetProps) {
            (function() use ($unsetProps) {
                foreach ($unsetProps as $unsetProp) {
                    unset($this->{$unsetProp});
                }
            })->bindTo($object, $class)();
        }

        return $object;
    }

    /**
     * Reads *initialized* object properties.
     *
     * Properties that are not initialized, or have been unset(), are not included in the array.
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

    /**
     * Writes an object's properties.
     *
     * This method does not support writing private properties in parent classes.
     *
     * @param object $object The object to write.
     * @param array  $values A map of property names to values.
     *
     * @return void
     */
    public function write(object $object, array $values) : void
    {
        (function() use ($values) {
            foreach ($values as $key => $value) {
                $this->{$key} = $value;
            }
        })->bindTo($object, $object)();
    }
}
