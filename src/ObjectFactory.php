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
     * This method returns an object whose *persistent* properties are not initialized.
     * Transient properties are still initialized to their default value, if any.
     *
     * @param ClassMetadata $classMetadata The class metadata of the entity or embeddable.
     *
     * @return object
     *
     * @throws \ReflectionException If the class does not exist.
     */
    public function instantiate(ClassMetadata $classMetadata) : object
    {
        $className = $classMetadata->className;

        if (isset($this->classes[$className])) {
            $reflectionClass = $this->classes[$className];
        } else {
            $reflectionClass = $this->classes[$className] = new \ReflectionClass($className);
        }

        $object = $reflectionClass->newInstanceWithoutConstructor();

        // Unset persistent properties
        // @todo PHP 7.4: for even better performance, only unset typed properties that have a default value,
        //       as unset() will have no effect on those that have no default value (will require a new metadata prop).

        (function() use ($classMetadata) {
            foreach ($classMetadata->properties as $prop) {
                unset($this->{$prop});
            }
        })->bindTo($object, $className)();

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
