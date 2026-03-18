<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->import(__DIR__ . '/vendor/brick/coding-standard/ecs.php');

    $libRootPath = realpath(__DIR__ . '/../..');

    $ecsConfig->paths([
        $libRootPath . '/src',
        $libRootPath . '/tests',
        $libRootPath . '/bin/orm-build',
        $libRootPath . '/tests-config.php',
        __FILE__,
    ]);

    $ecsConfig->skip([
        $libRootPath . '/src/ProxyTemplate.php',
        $libRootPath . '/src/RepositoryTemplate.php',
        $libRootPath . '/tests/Generated/',
    ]);
};
