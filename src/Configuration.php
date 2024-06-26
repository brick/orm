<?php

declare(strict_types=1);

namespace Brick\ORM;

class Configuration
{
    private string|null $proxyNamespace = null;

    private string|null $proxyDir = null;

    private string|null $repositoryNamespace = null;

    private string|null $repositoryDir = null;

    private string|null $classMetadataFile = null;

    private string|null $baseEntityNamespace = null;

    /**
     * @psalm-var array<class-string, ClassConfiguration>
     *
     * @var ClassConfiguration[]
     */
    private array $classes = [];

    /**
     * A map of entity/embeddable class names to lists of transient property names.
     *
     * @psalm-var array<class-string, list<string>>
     *
     * @var string[][]
     */
    private array $transientProperties = [];

    /**
     * A map of entity/embeddable class names to property names to PropertyMapping instances.
     *
     * The mappings are usually inferred from the PHP type, but can be overridden here.
     * This is typically used to map a mixed/array type property to a JSON column.
     *
     * @psalm-var array<class-string, array<string, PropertyMapping>>
     *
     * @var PropertyMapping[][]
     */
    private array $customPropertyMappings = [];

    /**
     * A map of entity/embeddable class names to property names to field names.
     *
     * @psalm-var array<class-string, array<string, string>>
     *
     * @var string[][]
     */
    private array $fieldNames = [];

    /**
     * A map of entity/embeddable class names to property names to field name prefixes.
     *
     * @psalm-var array<class-string, array<string, string>>
     *
     * @var string[][]
     */
    private array $fieldNamePrefixes = [];

    /**
     * A map of class names to custom property mapping classes.
     *
     * @var array<string, class-string<PropertyMapping>>
     */
    private array $customMappings = [];

    public function setProxyNamespace(string $proxyNamespace) : Configuration
    {
        $this->proxyNamespace = $proxyNamespace;

        return $this;
    }

    /**
     * @throws \LogicException
     */
    public function getProxyNamespace(string|null $entityClass = null) : string
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
     * @psalm-param class-string $entityClass
     *
     * @psalm-return class-string<Proxy>
     *
     * @psalm-suppress LessSpecificReturnStatement
     * @psalm-suppress MoreSpecificReturnType
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

    public function setProxyDir(string $proxyDir) : Configuration
    {
        $this->proxyDir = $proxyDir;

        return $this;
    }

    /**
     * @throws \LogicException
     */
    public function getProxyDir() : string
    {
        if ($this->proxyDir === null) {
            throw new \LogicException('Proxy dir is not set.');
        }

        return $this->proxyDir;
    }

    public function setRepositoryNamespace(string $repositoryNamespace) : Configuration
    {
        $this->repositoryNamespace = $repositoryNamespace;

        return $this;
    }

    /**
     * @throws \LogicException
     */
    public function getRepositoryNamespace(string|null $entityClass = null) : string
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

    public function setRepositoryDir(string $repositoryDir) : Configuration
    {
        $this->repositoryDir = $repositoryDir;

        return $this;
    }

    /**
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
     */
    public function setClassMetadataFile(string $classMetadataFile) : Configuration
    {
        if (substr($classMetadataFile, -4) !== '.php') {
            throw new \InvalidArgumentException('The ClassMetadata file path must have a .php extension.');
        }

        $this->classMetadataFile = $classMetadataFile;

        return $this;
    }

    /**
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
     */
    public function setBaseEntityNamespace(string $namespace) : Configuration
    {
        $this->baseEntityNamespace = $namespace;

        return $this;
    }

    public function getBaseEntityNamespace() : string|null
    {
        return $this->baseEntityNamespace;
    }

    /**
     * @psalm-param class-string $className
     */
    public function addEntity(string $className) : EntityConfiguration
    {
        $entityConfiguration = new EntityConfiguration($this, $className);
        $this->classes[$className] = $entityConfiguration;

        return $entityConfiguration;
    }

    /**
     * @psalm-param class-string $className
     */
    public function addEmbeddable(string $className) : EmbeddableConfiguration
    {
        $embeddableConfiguration = new EmbeddableConfiguration($this, $className);
        $this->classes[$className] = $embeddableConfiguration;

        return $embeddableConfiguration;
    }

    /**
     * Adds a custom mapping that applies by default to all properties of the given type.
     *
     * @psalm-param class-string $className
     * @psalm-param class-string<PropertyMapping> $propertyMapping
     *
     * @param string $className       The mapped class name.
     * @param string $propertyMapping The PropertyMapping implementation class name.
     */
    public function addCustomMapping(string $className, string $propertyMapping) : Configuration
    {
        $this->customMappings[$className] = $propertyMapping;

        return $this;
    }

    /**
     * @psalm-return array<string, class-string<PropertyMapping>>
     */
    public function getCustomMappings() : array
    {
        return $this->customMappings;
    }

    /**
     * Adds a custom property mapping for a specific property of a given entity/embeddable class.
     *
     * @todo Naming of addCustomMapping() / setCustomPropertyMapping() is a bit confusing
     *
     * @psalm-param class-string $class
     */
    public function setCustomPropertyMapping(string $class, string $property, PropertyMapping $mapping) : Configuration
    {
        $this->customPropertyMappings[$class][$property] = $mapping;

        return $this;
    }

    /**
     * @psalm-return array<class-string, array<string, PropertyMapping>>
     *
     * @return PropertyMapping[][]
     */
    public function getCustomPropertyMappings() : array
    {
        return $this->customPropertyMappings;
    }

    /**
     * @psalm-param class-string $class
     */
    public function setTransientProperties(string $class, string ...$properties) : Configuration
    {
        $this->transientProperties[$class] = array_values($properties);

        return $this;
    }

    /**
     * Returns the list of transient properties for the given class name.
     *
     * @psalm-param class-string $class
     *
     * @psalm-return list<string>
     *
     * @return string[]
     */
    public function getTransientProperties(string $class) : array
    {
        return $this->transientProperties[$class] ?? [];
    }

    /**
     * @psalm-param class-string $class
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
     * @psalm-return array<class-string, array<string, string>>
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
     * @psalm-param class-string $class
     */
    public function setFieldNamePrefix(string $class, string $property, string $fieldNamePrefix) : Configuration
    {
        $this->fieldNamePrefixes[$class][$property] = $fieldNamePrefix;

        return $this;
    }

    /**
     * @psalm-return array<class-string, array<string, string>>
     *
     * @return string[][]
     */
    public function getFieldNamePrefixes() : array
    {
        return $this->fieldNamePrefixes;
    }

    /**
     * Returns the class configurations, indexed by FQCN.
     *
     * @psalm-return array<class-string, ClassConfiguration>
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
     * @psalm-return array<class-string, EntityConfiguration>
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
