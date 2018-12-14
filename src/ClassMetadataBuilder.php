<?php

declare(strict_types=1);

namespace Brick\ORM;

/**
 * Builds ClassMetadata instances for all entities.
 */
class ClassMetadataBuilder
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var ClassMetadata[]
     */
    private $classMetadata;

    /**
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return ClassMetadata[] A map of FQCN to ClassMetadata instances for all entities.
     */
    public function build() : array
    {
        $this->classMetadata = [];

        $entityConfigurations = $this->configuration->getEntities();

        foreach ($entityConfigurations as $entityConfiguration) {
            foreach ($entityConfiguration->getClassHierarchy() as $className) {
                $this->classMetadata[$className] = new ClassMetadata();
            }
        }

        // This needs to be done in 2 steps, as references to all ClassMetadata instances must be available below.

        foreach ($entityConfigurations as $entityConfiguration) {
            foreach ($entityConfiguration->getClassHierarchy() as $className) {
                $this->fillClassMetadata($this->classMetadata[$className], $className, $entityConfiguration);
            }
        }

        return $this->classMetadata;
    }

    /**
     * @param ClassMetadata       $classMetadata
     * @param string              $className
     * @param EntityConfiguration $entityConfiguration
     *
     * @return void
     */
    private function fillClassMetadata(ClassMetadata $classMetadata, string $className, EntityConfiguration $entityConfiguration) : void
    {
        $reflectionClass = new \ReflectionClass($className);

        $classMetadata->className = $className;

        $classMetadata->discriminatorColumn = $entityConfiguration->getDiscriminatorColumn();
        $classMetadata->discriminatorValue = null;

        foreach ($entityConfiguration->getDiscriminatorMap() as $discriminatorValue => $targetClassName) {
            if ($targetClassName === $className) {
                $classMetadata->discriminatorValue = $discriminatorValue;
                break;
            }
        }

        $classMetadata->discriminatorMap = $entityConfiguration->getDiscriminatorMap();

        if ($reflectionClass->isAbstract()) {
            $classMetadata->proxyClassName = null;
        } else {
            // @todo don't just use entity short name: strip base entity namespace, add remaining namespace/dir to proxy!
            $classMetadata->proxyClassName = sprintf('%s\%sProxy', $this->configuration->getProxyNamespace(), $reflectionClass->getShortName());
        }

        $classMetadata->tableName = $entityConfiguration->getTableName();
        $classMetadata->isAutoIncrement = $entityConfiguration->isAutoIncrement();

        $persistentProperties = $entityConfiguration->getPersistentProperties($className);
        $identityProperties   = $entityConfiguration->getIdentityProperties();

        $classMetadata->properties = $persistentProperties;
        $classMetadata->idProperties = $identityProperties;
        $classMetadata->nonIdProperties = array_values(array_diff($persistentProperties, $identityProperties));

        $classMetadata->propertyMappings = [];

        foreach ($persistentProperties as $propertyName) {
            $propertyMapping = $entityConfiguration->getPropertyMapping($className, $propertyName, $this->classMetadata);
            $classMetadata->propertyMappings[$propertyName] = $propertyMapping;
        }
    }
}
