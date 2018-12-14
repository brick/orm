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

        foreach ($entityConfigurations as $className => $entityConfiguration) {
            $this->classMetadata[$className] = new ClassMetadata();
        }

        // This needs to be done in 2 steps, as references to all ClassMetadata instances must be available below.

        foreach ($entityConfigurations as $className => $entityConfiguration) {
            $this->fillClassMetadata($this->classMetadata[$className], $entityConfiguration);
        }

        return $this->classMetadata;
    }

    /**
     * @param ClassMetadata       $classMetadata
     * @param EntityConfiguration $entityConfiguration
     *
     * @return void
     */
    private function fillClassMetadata(ClassMetadata $classMetadata, EntityConfiguration $entityConfiguration) : void
    {
        $classMetadata->className = $entityConfiguration->getClassName();

        // @todo don't just use entity short name: strip base entity namespace, add remaining namespace/dir to proxy!
        $classMetadata->proxyClassName = sprintf('%s\%sProxy', $this->configuration->getProxyNamespace(), $entityConfiguration->getClassShortName());
        $classMetadata->tableName = $entityConfiguration->getTableName();
        $classMetadata->isAutoIncrement = $entityConfiguration->isAutoIncrement();

        $persistentProperties = $entityConfiguration->getPersistentProperties();
        $identityProperties   = $entityConfiguration->getIdentityProperties();

        $classMetadata->properties = $persistentProperties;
        $classMetadata->idProperties = $identityProperties;
        $classMetadata->nonIdProperties = array_values(array_diff($persistentProperties, $identityProperties));

        $classMetadata->propertyMappings = [];

        foreach ($persistentProperties as $property) {
            $propertyMapping = $entityConfiguration->getPropertyMapping($property, $this->classMetadata);
            $classMetadata->propertyMappings[$property] = $propertyMapping;
        }
    }
}
