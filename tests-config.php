<?php

declare(strict_types=1);

return (function() {
    $config = new \Brick\ORM\Configuration();

    $config->setRepositoryNamespace('Brick\ORM\Tests\Generated\Repository');
    $config->setRepositoryDir(__DIR__ . '/tests/Generated/Repository');

    $config->setProxyNamespace('Brick\ORM\Tests\Generated\Proxy');
    $config->setProxyDir(__DIR__ . '/tests/Generated/Proxy');

    $config->setBaseEntityNamespace('Brick\ORM\Tests\Resources\Models');
    $config->setClassMetadataFile(__DIR__ . '/tests/Generated/ClassMetadata.php');

    $config->addEntity(\Brick\ORM\Tests\Resources\Models\User::class)
        ->setIdentityProperties('id')
        ->setAutoIncrement();

    $config->addEntity(\Brick\ORM\Tests\Resources\Models\Follow::class)
        ->setIdentityProperties('follower', 'followee');

    $config->addEntity(\Brick\ORM\Tests\Resources\Models\Country::class)
        ->setIdentityProperties('code');

    $config->addEmbeddable(\Brick\ORM\Tests\Resources\Models\Address::class);
    $config->addEmbeddable(\Brick\ORM\Tests\Resources\Models\GeoAddress::class);

    $config->addCustomMapping(\Brick\ORM\Tests\Resources\Objects\Geometry::class, \Brick\ORM\Tests\Resources\Mappings\GeometryMapping::class);

    // Set transient properties
    $config->setTransientProperties(\Brick\ORM\Tests\Resources\Models\User::class, 'transient');

    // Override field names / prefixes
    $config->setFieldName(\Brick\ORM\Tests\Resources\Models\Address::class, 'postcode', 'zipcode');
    $config->setFieldNamePrefix(\Brick\ORM\Tests\Resources\Models\User::class, 'billingAddress', '');

    return $config;
})();
