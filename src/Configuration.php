<?php

declare(strict_types=1);

namespace Brick\ORM;

class Configuration
{
    /**
     * @var string|null
     */
    private $proxyNamespace;

    /**
     * @var string|null
     */
    private $proxyDir;

    /**
     * @var string|null
     */
    private $repositoryNamespace;

    /**
     * @var string|null
     */
    private $repositoryDir;

    /**
     * @var string|null
     */
    private $classMetadataFile;

    /**
     * @var string|null
     */
    private $baseEntityNamespace;

    /**
     * @var ClassConfiguration[]
     */
    private $classes = [];

    /**
     * A map of entity/embeddable class names to lists of transient property names.
     *
     * @var string[][]
     */
    private $transientProperties = [];

    /**
     * A map of entity/embeddable class names to property names to field names.
     *
     * @var string[][]
     */
    private $fieldNames = [];

    /**
     * A map of entity/embeddable class names to property names to field name prefixes.
     *
     * @var string[][]
     */
    private $fieldNamePrefixes = [];

    /**
     * A map of class names to custom property mapping classes.
     *
     * @var PropertyMapping[]
     */
    private $customMappings = [];

    /**
     * @param string $proxyNamespace
     *
     * @return Configuration
     */
    public function setProxyNamespace(string $proxyNamespace) : Configuration
    {
        $this->proxyNamespace = $proxyNamespace;

        return $this;
    }

    /**
     * @param string|null $entityClass
     *
     * @return string
     *
     * @throws \LogicException
     */
    public function getProxyNamespace(?string $entityClass = null) : string
    {
        if ($this->proxyNamespace === null) {
            throw new \LogicException('Proxy namespace is not set.');
        }

        if ($entityClass === null) {
            return $this->proxyNamespace;
        }

        if ($this->baseEntityNamespace !== null) {
            $baseNamespace = $this->baseEntityNamespace . '\\';
            $length = strlen($baseNamespace);

            if (substr($entityClass, 0, $length) !== $baseNamespace) {
                throw new \LogicException(sprintf('%s is not in namespace %s.', $entityClass, $this->baseEntityNamespace));
            }

            $entityClass = substr($entityClass, $length);
        }

        $pos = strrpos($entityClass, '\\');

        if ($pos === false) {
            return $this->proxyNamespace;
        }

        return $this->proxyNamespace . '\\' . substr($entityClass, 0, $pos);
    }

    /**
     * Returns the proxy class name for the given entity class name.
     *
     * @param string $entityClass the FQCN of the entity.
     *
     * @return string The FQCN of the proxy.
     *
     * @throws \LogicException
     */
    public function getProxyClassName(string $entityClass) : string
    {
        if ($this->baseEntityNamespace !== null) {
            $baseNamespace = $this->baseEntityNamespace . '\\';
            $length = strlen($baseNamespace);

            if (substr($entityClass, 0, $length) !== $baseNamespace) {
                throw new \LogicException(sprintf('%s is not in namespace %s.', $entityClass, $this->baseEntityNamespace));
            }

            $entityClass = substr($entityClass, $length);
        }

        return $this->getProxyNamespace() . '\\' . $entityClass . 'Proxy';
    }

    /**
     * @param string $entityClass
     *
     * @return string
     */
    public function getProxyFileName(string $entityClass) : string
    {
        if ($this->baseEntityNamespace !== null) {
            $baseNamespace = $this->baseEntityNamespace . '\\';
            $length = strlen($baseNamespace);

            if (substr($entityClass, 0, $length) !== $baseNamespace) {
                throw new \LogicException(sprintf('%s is not in namespace %s.', $entityClass, $this->baseEntityNamespace));
            }

            $entityClass = substr($entityClass, $length);
        }

        return $this->getProxyDir() . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $entityClass) . 'Proxy.php';
    }

    /**
     * @param string $proxyDir
     *
     * @return Configuration
     */
    public function setProxyDir(string $proxyDir) : Configuration
    {
        $this->proxyDir = $proxyDir;

        return $this;
    }

    /**
     * @return string
     *
     * @throws \LogicException
     */
    public function getProxyDir() : string
    {
        if ($this->proxyDir === null) {
            throw new \LogicException('Proxy dir is not set.');
        }

        return $this->proxyDir;
    }

    /**
     * @param string $repositoryNamespace
     *
     * @return Configuration
     */
    public function setRepositoryNamespace(string $repositoryNamespace) : Configuration
    {
        $this->repositoryNamespace = $repositoryNamespace;

        return $this;
    }

    /**
     * @param string|null $entityClass
     *
     * @return string
     *
     * @throws \LogicException
     */
    public function getRepositoryNamespace(?string $entityClass = null) : string
    {
        if ($this->repositoryNamespace === null) {
            throw new \LogicException('Repository namespace is not set.');
        }

        if ($entityClass === null) {
            return $this->repositoryNamespace;
        }

        if ($this->baseEntityNamespace !== null) {
            $baseNamespace = $this->baseEntityNamespace . '\\';
            $length = strlen($baseNamespace);

            if (substr($entityClass, 0, $length) !== $baseNamespace) {
                throw new \LogicException(sprintf('%s is not in namespace %s.', $entityClass, $this->baseEntityNamespace));
            }

            $entityClass = substr($entityClass, $length);
        }

        $pos = strrpos($entityClass, '\\');

        if ($pos === false) {
            return $this->repositoryNamespace;
        }

        return $this->repositoryNamespace . '\\' . substr($entityClass, 0, $pos);
    }

    /**
     * @param string $entityClass
     *
     * @return string
     */
    public function getRepositoryFileName(string $entityClass) : string
    {
        if ($this->baseEntityNamespace !== null) {
            $baseNamespace = $this->baseEntityNamespace . '\\';
            $length = strlen($baseNamespace);

            if (substr($entityClass, 0, $length) !== $baseNamespace) {
                throw new \LogicException(sprintf('%s is not in namespace %s.', $entityClass, $this->baseEntityNamespace));
            }

            $entityClass = substr($entityClass, $length);
        }

        return $this->getRepositoryDir() . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $entityClass) . 'Repository.php';
    }

    /**
     * @param string $repositoryDir
     *
     * @return Configuration
     */
    public function setRepositoryDir(string $repositoryDir) : Configuration
    {
        $this->repositoryDir = $repositoryDir;

        return $this;
    }

    /**
     * @return string
     *
     * @throws \LogicException
     */
    public function getRepositoryDir() : string
    {
        if ($this->repositoryDir === null) {
            throw new \LogicException('Repository dir is not set.');
        }

        return $this->repositoryDir;
    }

    /**
     * Sets the path to the PHP file where the ClassMetadata will be stored.
     *
     * @param string $classMetadataFile
     *
     * @return Configuration
     */
    public function setClassMetadataFile(string $classMetadataFile) : Configuration
    {
        $this->classMetadataFile = $classMetadataFile;

        if (substr($classMetadataFile, -4) !== '.php') {
            throw new \InvalidArgumentException('The ClassMetadata file path must have a .php extension.');
        }

        return $this;
    }

    /**
     * @return string
     *
     * @throws \LogicException
     */
    public function getClassMetadataFile() : string
    {
        if ($this->classMetadataFile === null) {
            throw new \LogicException('ClassMetadata file path is not set.');
        }

        return $this->classMetadataFile;
    }

    /**
     * Sets the base namespace all entities live in.
     *
     * This is optional, but restricts the number of sub-namespaces (and subdirs) created for repositories and proxies.
     *
     * For example, by default App\Model\User's repository would live in RepositoryNamespace\App\Model\UserRepository,
     * while with a base entity namespace of App\Model it would live in RepositoryNamespace\UserRepository.
     *
     * @param string $namespace
     *
     * @return Configuration
     */
    public function setBaseEntityNamespace(string $namespace) : Configuration
    {
        $this->baseEntityNamespace = $namespace;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getBaseEntityNamespace() : ?string
    {
        return $this->baseEntityNamespace;
    }

    /**
     * @param string $className
     *
     * @return EntityConfiguration
     */
    public function addEntity(string $className) : EntityConfiguration
    {
        $entityConfiguration = new EntityConfiguration($this, $className);
        $this->classes[$className] = $entityConfiguration;

        return $entityConfiguration;
    }

    /**
     * @param string $className
     *
     * @return EmbeddableConfiguration
     */
    public function addEmbeddable(string $className) : EmbeddableConfiguration
    {
        $embeddableConfiguration = new EmbeddableConfiguration($this, $className);
        $this->classes[$className] = $embeddableConfiguration;

        return $embeddableConfiguration;
    }

    /**
     * @param string $className       The mapped class name.
     * @param string $propertyMapping The PropertyMapping implementation class name.
     *
     * @return Configuration
     */
    public function addCustomMapping(string $className, string $propertyMapping) : Configuration
    {
        $this->customMappings[$className] = $propertyMapping;

        return $this;
    }

    /**
     * @return PropertyMapping[}
     */
    public function getCustomMappings() : array
    {
        return $this->customMappings;
    }

    /**
     * @param string $class
     * @param string ...$properties
     *
     * @return Configuration
     */
    public function setTransientProperties(string $class, string ...$properties) : Configuration
    {
        $this->transientProperties[$class] = $properties;

        return $this;
    }

    /**
     * Returns the list of transient properties for the given class name.
     *
     * @param string $class
     *
     * @return string[]
     */
    public function getTransientProperties(string $class) : array
    {
        return $this->transientProperties[$class] ?? [];
    }

    /**
     * @param string $class
     * @param string $property
     * @param string $fieldName
     *
     * @return Configuration
     */
    public function setFieldName(string $class, string $property, string $fieldName) : Configuration
    {
        $this->fieldNames[$class][$property] = $fieldName;

        return $this;
    }

    /**
     * Sets custom field names for builtin type properties.
     *
     * If not set, the field name defaults to the property name.
     *
     * @return string[][]
     */
    public function getFieldNames() : array
    {
        return $this->fieldNames;
    }

    /**
     * Sets field name prefixes for entity/embeddable properties.
     *
     * If not set, the field name prefix defaults to the property name followed by an underscore character.
     *
     * @param string $class
     * @param string $property
     * @param string $fieldNamePrefix
     *
     * @return Configuration
     */
    public function setFieldNamePrefix(string $class, string $property, string $fieldNamePrefix) : Configuration
    {
        $this->fieldNamePrefixes[$class][$property] = $fieldNamePrefix;

        return $this;
    }

    /**
     * @return string[][]
     */
    public function getFieldNamePrefixes() : array
    {
        return $this->fieldNamePrefixes;
    }

    /**
     * Returns the class configurations, indexed by FQCN.
     *
     * @return ClassConfiguration[]
     */
    public function getClasses() : array
    {
        return $this->classes;
    }

    /**
     * Returns the entity configurations, indexed by FQCN.
     *
     * @return EntityConfiguration[]
     */
    public function getEntities() : array
    {
        $entityConfigurations = [];

        foreach ($this->classes as $className => $classConfiguration) {
            if ($classConfiguration instanceof EntityConfiguration) {
                $entityConfigurations[$className] = $classConfiguration;
            }
        }

        return $entityConfigurations;
    }
}
