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
     * @var EntityConfiguration[]
     */
    private $entities = [];

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
     * Returns the entity configurations, indexed by FQCN.
     *
     * @return EntityConfiguration[]
     */
    public function getEntities() : array
    {
        return $this->entities;
    }
}
