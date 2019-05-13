<?php

declare(strict_types=1);

use Brick\ORM\PropertyMapping\JsonMapping;
use Brick\ORM\Tests\Resources\Models;

return (function() {
    $config = new \Brick\ORM\Configuration();

    $config->setRepositoryNamespace('Brick\ORM\Tests\Generated\Repository');
    $config->setRepositoryDir(__DIR__ . '/tests/Generated/Repository');

    $config->setProxyNamespace('Brick\ORM\Tests\Generated\Proxy');
    $config->setProxyDir(__DIR__ . '/tests/Generated/Proxy');

    $config->setBaseEntityNamespace('Brick\ORM\Tests\Resources\Models');
    $config->setClassMetadataFile(__DIR__ . '/tests/Generated/ClassMetadata.php');

    $config->addEntity(Models\User::class)
        ->setIdentityProperties('id')
        ->setAutoIncrement();

    $config->addEntity(Models\Follow::class)
        ->setIdentityProperties('follower', 'followee');

    $config->addEntity(Models\Country::class)
        ->setIdentityProperties('code');

    $config->addEntity(Brick\ORM\Tests\Resources\Models\Event::class)
        ->setIdentityProperties('id')
        ->setAutoIncrement()
        ->setInheritanceMapping('type', [
            'CreateCountry'           => Models\Event\CountryEvent\CreateCountryEvent::class,
            'EditCountryName'         => Models\Event\CountryEvent\EditCountryNameEvent::class,
            'CreateUser'              => Models\Event\UserEvent\CreateUserEvent::class,
            'EditUserBillingAddress'  => Models\Event\UserEvent\EditUserBillingAddressEvent::class,
            'EditUserDeliveryAddress' => Models\Event\UserEvent\EditUserDeliveryAddressEvent::class,
            'EditUserName'            => Models\Event\UserEvent\EditUserNameEvent::class,
            'FollowUser'              => Models\Event\FollowUserEvent::class
        ]);

    $config->addEmbeddable(Models\Address::class);
    $config->addEmbeddable(Models\GeoAddress::class);

    $config->addCustomMapping(\Brick\ORM\Tests\Resources\Objects\Geometry::class, \Brick\ORM\Tests\Resources\Mappings\GeometryMapping::class);

    // Set transient properties
    $config->setTransientProperties(Models\User::class, 'transient');

    // Override field names / prefixes
    $config->setFieldName(Models\Address::class, 'postcode', 'zipcode');
    $config->setFieldNamePrefix(Models\User::class, 'billingAddress', '');

    $config->setCustomPropertyMapping(Models\User::class, 'data', new JsonMapping('data', false, true));

    return $config;
})();
