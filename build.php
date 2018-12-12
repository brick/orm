<?php

declare(strict_types=1);

/**
 * This script builds the class metadata, repositories and proxies.
 * It must be called after every configuration change, or entity property change.
 *
 * The only argument to this script is the path to a PHP file that returns a Configuration instance.
 */

use Brick\ORM\ClassMetadata;
use Brick\ORM\ClassMetadataBuilder;
use Brick\ORM\Configuration;
use Brick\ORM\ProxyBuilder;
use Brick\ORM\RepositoryBuilder;

require __DIR__ . '/../../autoload.php';

if ($argc !== 2) {
    echo "Usage: {$argv[0]} path/to/configuration.php", PHP_EOL;
    exit(1);
}

(function(string $path) {
    /** @var Configuration $configuration */
    $configuration = require $path;

    // Build class metadata

    /** @var ClassMetadata[] $classMetadata */
    $classMetadata = (function() use ($configuration) {
        $builder = new ClassMetadataBuilder($configuration);
        $classMetadata = $builder->build();

        $php = '<?php return unserialize(' . var_export(serialize($classMetadata), true) . ");\n";

        file_put_contents($configuration->getClassMetadataFile(), $php);

        return $classMetadata;
    })();

    // Build repositories

    (function() use ($configuration, $classMetadata) {
        foreach ($configuration->getEntities() as $className => $entityConfiguration) {
            $builder = new RepositoryBuilder();
            $builder->setRepositoryNamespace($configuration->getRepositoryNamespace());
            $builder->setEntityClassName($className);

            $identityProps = [];

            foreach ($classMetadata[$className]->idProperties as $idProperty) {
                $identityProps[$idProperty] = $classMetadata[$className]->properties[$idProperty]->getType();
            }

            $builder->setIdentityProps($identityProps);

            $path = $configuration->getRepositoryDir()
                . DIRECTORY_SEPARATOR
                . $entityConfiguration->getClassShortName()
                . 'Repository.php';

            file_put_contents($path, $builder->build());
        }
    })();

    // Build proxies

    (function() use ($configuration, $classMetadata) {
        foreach ($configuration->getEntities() as $className => $entityConfiguration) {
            $builder = new ProxyBuilder();
            $builder->setProxyNamespace($configuration->getProxyNamespace());
            $builder->setEntityClassName($className);
            $builder->setNonIdProps($classMetadata[$className]->nonIdProperties);

            $path = $configuration->getProxyDir()
                . DIRECTORY_SEPARATOR
                . $entityConfiguration->getClassShortName()
                . 'Proxy.php';

            file_put_contents($path, $builder->build());
        }
    })();
})($argv[1]);
