<?php

declare(strict_types=1);

namespace Brick\ORM;

use ReflectionNamedType;

/**
 * Creates, reads and writes persistent objects.
 *
 * Important note: this does not aim to support private properties in parent classes.
 *
 * This class is performance sensitive, and uses techniques benchmarked here:
 * https://gist.github.com/BenMorel/9a920538862e4df0d7041f8812f069e5
 */
class ObjectFactory
{
    /**
     * A map of fully qualified class name to ReflectionClass instance.
     *
     * @var \ReflectionClass[]
     */
    private array $classes = [];

    /**
     * A map of full qualified class name to map of property name to Closure.
     *
     * Each closure converts a property value to the correct type.
     *
     * @var \Closure[][]
     */
    private array $propertyConverters = [];

    /**
     * Instantiates an empty object, without calling the class constructor.
     *
     * By default, this method returns an object whose *persistent* properties are not initialized.
     * Transient properties are still initialized to their default value, if any.
     * Properties may be initialized by passing a map of property name to value.
     *
     * @param ClassMetadata $classMetadata The class metadata of the entity or embeddable.
     * @param array         $values        An optional map of property name to value to write.
     *
     * @return object
     *
     * @throws \ReflectionException If the class does not exist.
     */
    public function instantiate(ClassMetadata $classMetadata, array $values = []) : object
    {
        $className = $classMetadata->className;

        if (isset($this->classes[$className])) {
            $reflectionClass = $this->classes[$className];
        } else {
            $reflectionClass = $this->classes[$className] = new \ReflectionClass($className);
        }

        $object = $reflectionClass->newInstanceWithoutConstructor();

        /** @psalm-suppress PossiblyInvalidFunctionCall bindTo() should never return false here */
        (function() use ($classMetadata, $values, $reflectionClass) {
            // Unset persistent properties
            // @todo PHP 7.4: for even better performance, only unset typed properties that have a default value, as
            //       unset() will have no effect on those that have no default value (will require a new metadata prop).
            foreach ($classMetadata->properties as $prop) {
                unset($this->{$prop});
            }

            // Set values
            foreach ($values as $key => $value) {
                if ($value === null) {
                    // @todo temporary fix: do not set null values when typed property is not nullable;
                    //       needs investigation to see why these null values are being passed in the first place

                    /** @var \ReflectionType|null $reflectionType */
                    $reflectionType = $reflectionClass->getProperty($key)->getType();

                    if ($reflectionType !== null && ! $reflectionType->allowsNull()) {
                        continue;
                    }
                }

                $this->{$key} = $value;
            }
        })->bindTo($object, $className)();

        return $object;
    }

    /**
     * Instantiates a data transfer object with a nested array of scalar values.
     *
     * The class must have public properties only, and no constructor.
     *
     * @template T
     *
     * @psalm-param class-string<T> $className
     * @psalm-param array<string, mixed> $values
     *
     * @psalm-return T
     *
     * @throws \ReflectionException      If the class does not exist.
     * @throws \InvalidArgumentException If the class is not a valid DTO or an unexpected value is found.
     */
    public function instantiateDTO(string $className, array $values) : object
    {
        $propertyConverters = $this->getPropertyConverters($className);

        $object = new $className;

        foreach ($values as $name => $value) {
            if (! isset($propertyConverters[$name])) {
                throw new \InvalidArgumentException(sprintf('There is no property named $%s in class %s.', $name, $className));
            }

            $propertyConverter = $propertyConverters[$name];
            $object->{$name} = $propertyConverter($value);
        }

        return $object;
    }

    /**
     * @psalm-param class-string $className
     *
     * @return \Closure[]
     *
     * @throws \ReflectionException      If the class does not exist.
     * @throws \InvalidArgumentException If the class is not a valid DTO or an unexpected value is found.
     */
    private function getPropertyConverters(string $className) : array
    {
        if (isset($this->propertyConverters[$className])) {
            return $this->propertyConverters[$className];
        }

        $reflectionClass = new \ReflectionClass($className);

        if ($reflectionClass->isAbstract()) {
            throw new \InvalidArgumentException(sprintf('Cannot instantiate abstract class %s.', $className));
        }

        if ($reflectionClass->isInterface()) {
            throw new \InvalidArgumentException(sprintf('Cannot instantiate interface %s.', $className));
        }

        if ($reflectionClass->isInternal()) {
            throw new \InvalidArgumentException(sprintf('Cannot instantiate internal class %s.', $className));
        }

        if ($reflectionClass->getConstructor() !== null) {
            throw new \InvalidArgumentException(sprintf('Class %s must not have a constructor.', $className));
        }

        $properties = $reflectionClass->getProperties();

        $result = [];

        foreach ($properties as $property) {
            $name = $property->getName();

            if ($property->isStatic()) {
                throw new \InvalidArgumentException(sprintf('Property $%s of class %s must not be static.', $name, $className));
            }

            if (! $property->isPublic()) {
                throw new \InvalidArgumentException(sprintf('Property $%s of class %s must be public.', $name, $className));
            }

            $result[$name] = $this->getPropertyValueConverter($property);
        }

        $this->propertyConverters[$className] = $result;

        return $result;
    }

    /**
     * @psalm-return Closure(mixed): mixed
     *
     * @psalm-suppress MissingClosureParamType
     * @psalm-suppress MissingClosureReturnType
     *
     * @throws \InvalidArgumentException If an unexpected value is found.
     */
    private function getPropertyValueConverter(\ReflectionProperty $property) : \Closure
    {
        $type = $property->getType();

        $propertyName = $property->getName();
        $className = $property->getDeclaringClass()->getName();

        if ($type instanceof ReflectionNamedType) {
            $propertyType = $type->getName();

            if ($type->isBuiltin()) {
                return match ($propertyType) {
                    'string' => fn ($value) => $value,
                    'int'    => fn ($value) => (int) $value,
                    'float'  => fn ($value) => (float) $value,
                    'bool'   => fn ($value) => (bool) $value,
                    default  => throw new \InvalidArgumentException(sprintf('Unexpected non-scalar type "%s" for property $%s in class %s.', $propertyType, $propertyName, $className))
                };
            }

            return function($value) use ($propertyName, $className, $type) {
                if (! is_array($value)) {
                    throw new \InvalidArgumentException(sprintf('Expected array for property $%s of class %s, got %s.', $propertyName, $className, gettype($value)));
                }

                /** @psalm-var class-string $typeName */
                $typeName = $type->getName();

                return $this->instantiateDTO($typeName, $value);
            };
        }

        return fn ($value) => $value;
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
        /** @psalm-suppress PossiblyInvalidFunctionCall bindTo() should never return false here */
        return (function() {
            return get_object_vars($this);
        })->bindTo($object, $object)();
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
        /** @psalm-suppress PossiblyInvalidFunctionCall bindTo() should never return false here */
        (function() use ($values) {
            foreach ($values as $key => $value) {
                $this->{$key} = $value;
            }
        })->bindTo($object, $object)();
    }
}
