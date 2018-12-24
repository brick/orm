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
     * @var EntityMetadata[]
     */
    private $entityMetadata;

    /**
     * @var EmbeddableMetadata[]
     */
    private $embeddableMetadata;

    /**
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return EntityMetadata[] A map of FQCN to EntityMetadata instances for all entities.
     */
    public function build() : array
    {
        $this->entityMetadata = [];
        $this->embeddableMetadata = [];

        $classConfigurations = $this->configuration->getClasses();

        foreach ($classConfigurations as $classConfiguration) {
            if ($classConfiguration instanceof EntityConfiguration) {
                foreach ($classConfiguration->getClassHierarchy() as $className) {
                    $this->entityMetadata[$className] = new EntityMetadata();
                }
            } elseif ($classConfiguration instanceof EmbeddableConfiguration) {
                $this->embeddableMetadata[$classConfiguration->getClassName()] = new EmbeddableMetadata();
            }
        }

        // This needs to be done in 2 steps, as references to all ClassMetadata instances must be available below.

        foreach ($classConfigurations as $classConfiguration) {
            if ($classConfiguration instanceof EntityConfiguration) {
                foreach ($classConfiguration->getClassHierarchy() as $className) {
                    $this->fillEntityMetadata($this->entityMetadata[$className], $className, $classConfiguration);
                }
            } elseif ($classConfiguration instanceof EmbeddableConfiguration) {
                $className = $classConfiguration->getClassName();
                $this->fillEmbeddableMetadata($this->embeddableMetadata[$className], $className, $classConfiguration);
            }
        }

        return $this->entityMetadata;
    }

    /**
     * @param EntityMetadata      $classMetadata
     * @param string              $className
     * @param EntityConfiguration $entityConfiguration
     *
     * @return void
     */
    private function fillEntityMetadata(EntityMetadata $classMetadata, string $className, EntityConfiguration $entityConfiguration) : void
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
            $classMetadata->proxyClassName = $this->configuration->getProxyClassName($className);
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
            $propertyMapping = $entityConfiguration->getPropertyMapping($className, $propertyName, $this->entityMetadata, $this->embeddableMetadata);
            $classMetadata->propertyMappings[$propertyName] = $propertyMapping;
        }
    }

    /**
     * @param EmbeddableMetadata      $classMetadata
     * @param string                  $className
     * @param EmbeddableConfiguration $embeddableConfiguration
     *
     * @return void
     */
    private function fillEmbeddableMetadata(EmbeddableMetadata $classMetadata, string $className, EmbeddableConfiguration $embeddableConfiguration) : void
    {
        $classMetadata->className = $className;

        $persistentProperties = $embeddableConfiguration->getPersistentProperties($className);

        $classMetadata->properties = $persistentProperties;

        $classMetadata->propertyMappings = [];

        foreach ($persistentProperties as $propertyName) {
            $propertyMapping = $embeddableConfiguration->getPropertyMapping($className, $propertyName, $this->entityMetadata, $this->embeddableMetadata);
            $classMetadata->propertyMappings[$propertyName] = $propertyMapping;
        }
    }
}
