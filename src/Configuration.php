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
     * @var EntityConfiguration[]
     */
    private $entities = [];

    /**
     * A map of entity class names to lists of property names.
     *
     * @var string[][]
     */
    private $transientProperties = [];

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
     * @return string
     *
     * @throws \LogicException
     */
    public function getProxyNamespace() : string
    {
        if ($this->proxyNamespace === null) {
            throw new \LogicException('Proxy namespace is not set.');
        }

        return $this->proxyNamespace;
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
     * @return string
     *
     * @throws \LogicException
     */
    public function getRepositoryNamespace() : string
    {
        if ($this->repositoryNamespace === null) {
            throw new \LogicException('Repository namespace is not set.');
        }

        return $this->repositoryNamespace;
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
     * @param string $className
     *
     * @return EntityConfiguration
     */
    public function addEntity(string $className) : EntityConfiguration
    {
        $entityConfiguration = new EntityConfiguration($this, $className);
        $this->entities[$className] = $entityConfiguration;

        return $entityConfiguration;
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
     * Returns the entity configurations, indexed by FQCN.
     *
     * @return EntityConfiguration[]
     */
    public function getEntities() : array
    {
        return $this->entities;
    }
}
