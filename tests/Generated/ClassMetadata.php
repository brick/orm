<?php return unserialize('a:3:{s:37:"Brick\\ORM\\Tests\\Resources\\Models\\User";O:24:"Brick\\ORM\\EntityMetadata":11:{s:19:"discriminatorColumn";N;s:18:"discriminatorValue";N;s:16:"discriminatorMap";a:0:{}s:14:"proxyClassName";s:41:"Brick\\ORM\\Tests\\Generated\\Proxy\\UserProxy";s:9:"tableName";s:4:"User";s:12:"idProperties";a:1:{i:0;s:2:"id";}s:15:"nonIdProperties";a:3:{i:0;s:4:"name";i:1;s:14:"billingAddress";i:2;s:15:"deliveryAddress";}s:15:"isAutoIncrement";b:1;s:9:"className";s:37:"Brick\\ORM\\Tests\\Resources\\Models\\User";s:10:"properties";a:4:{i:0;s:2:"id";i:1;s:4:"name";i:2;s:14:"billingAddress";i:3;s:15:"deliveryAddress";}s:16:"propertyMappings";a:4:{s:2:"id";O:36:"Brick\\ORM\\PropertyMapping\\IntMapping":2:{s:9:"fieldName";s:2:"id";s:10:"isNullable";b:0;}s:4:"name";O:39:"Brick\\ORM\\PropertyMapping\\StringMapping":2:{s:9:"fieldName";s:4:"name";s:10:"isNullable";b:0;}s:14:"billingAddress";O:43:"Brick\\ORM\\PropertyMapping\\EmbeddableMapping":3:{s:13:"classMetadata";O:28:"Brick\\ORM\\EmbeddableMetadata":3:{s:9:"className";s:40:"Brick\\ORM\\Tests\\Resources\\Models\\Address";s:10:"properties";a:5:{i:0;s:6:"street";i:1;s:4:"city";i:2;s:8:"postcode";i:3;s:7:"country";i:4;s:7:"isPoBox";}s:16:"propertyMappings";a:5:{s:6:"street";O:39:"Brick\\ORM\\PropertyMapping\\StringMapping":2:{s:9:"fieldName";s:6:"street";s:10:"isNullable";b:0;}s:4:"city";O:39:"Brick\\ORM\\PropertyMapping\\StringMapping":2:{s:9:"fieldName";s:4:"city";s:10:"isNullable";b:0;}s:8:"postcode";O:39:"Brick\\ORM\\PropertyMapping\\StringMapping":2:{s:9:"fieldName";s:7:"zipcode";s:10:"isNullable";b:1;}s:7:"country";O:39:"Brick\\ORM\\PropertyMapping\\EntityMapping":3:{s:13:"classMetadata";O:24:"Brick\\ORM\\EntityMetadata":11:{s:19:"discriminatorColumn";N;s:18:"discriminatorValue";N;s:16:"discriminatorMap";a:0:{}s:14:"proxyClassName";s:44:"Brick\\ORM\\Tests\\Generated\\Proxy\\CountryProxy";s:9:"tableName";s:7:"Country";s:12:"idProperties";a:1:{i:0;s:4:"code";}s:15:"nonIdProperties";a:1:{i:0;s:4:"name";}s:15:"isAutoIncrement";b:0;s:9:"className";s:40:"Brick\\ORM\\Tests\\Resources\\Models\\Country";s:10:"properties";a:2:{i:0;s:4:"code";i:1;s:4:"name";}s:16:"propertyMappings";a:2:{s:4:"code";O:39:"Brick\\ORM\\PropertyMapping\\StringMapping":2:{s:9:"fieldName";s:4:"code";s:10:"isNullable";b:0;}s:4:"name";O:39:"Brick\\ORM\\PropertyMapping\\StringMapping":2:{s:9:"fieldName";s:4:"name";s:10:"isNullable";b:0;}}}s:15:"fieldNamePrefix";s:8:"country_";s:10:"isNullable";b:0;}s:7:"isPoBox";O:37:"Brick\\ORM\\PropertyMapping\\BoolMapping":2:{s:9:"fieldName";s:7:"isPoBox";s:10:"isNullable";b:0;}}}s:15:"fieldNamePrefix";s:0:"";s:10:"isNullable";b:1;}s:15:"deliveryAddress";O:43:"Brick\\ORM\\PropertyMapping\\EmbeddableMapping":3:{s:13:"classMetadata";O:28:"Brick\\ORM\\EmbeddableMetadata":3:{s:9:"className";s:43:"Brick\\ORM\\Tests\\Resources\\Models\\GeoAddress";s:10:"properties";a:2:{i:0;s:7:"address";i:1;s:8:"location";}s:16:"propertyMappings";a:2:{s:7:"address";O:43:"Brick\\ORM\\PropertyMapping\\EmbeddableMapping":3:{s:13:"classMetadata";r:29;s:15:"fieldNamePrefix";s:8:"address_";s:10:"isNullable";b:0;}s:8:"location";O:50:"Brick\\ORM\\Tests\\Resources\\Mappings\\GeometryMapping":2:{s:12:"' . "\0" . '*' . "\0" . 'fieldName";s:8:"location";s:13:"' . "\0" . '*' . "\0" . 'isNullable";b:0;}}}s:15:"fieldNamePrefix";s:16:"deliveryAddress_";s:10:"isNullable";b:1;}}}s:39:"Brick\\ORM\\Tests\\Resources\\Models\\Follow";O:24:"Brick\\ORM\\EntityMetadata":11:{s:19:"discriminatorColumn";N;s:18:"discriminatorValue";N;s:16:"discriminatorMap";a:0:{}s:14:"proxyClassName";s:43:"Brick\\ORM\\Tests\\Generated\\Proxy\\FollowProxy";s:9:"tableName";s:6:"Follow";s:12:"idProperties";a:2:{i:0;s:8:"follower";i:1;s:8:"followee";}s:15:"nonIdProperties";a:1:{i:0;s:5:"since";}s:15:"isAutoIncrement";b:0;s:9:"className";s:39:"Brick\\ORM\\Tests\\Resources\\Models\\Follow";s:10:"properties";a:3:{i:0;s:8:"follower";i:1;s:8:"followee";i:2;s:5:"since";}s:16:"propertyMappings";a:3:{s:8:"follower";O:39:"Brick\\ORM\\PropertyMapping\\EntityMapping":3:{s:13:"classMetadata";r:2;s:15:"fieldNamePrefix";s:9:"follower_";s:10:"isNullable";b:0;}s:8:"followee";O:39:"Brick\\ORM\\PropertyMapping\\EntityMapping":3:{s:13:"classMetadata";r:2;s:15:"fieldNamePrefix";s:9:"followee_";s:10:"isNullable";b:0;}s:5:"since";O:36:"Brick\\ORM\\PropertyMapping\\IntMapping":2:{s:9:"fieldName";s:5:"since";s:10:"isNullable";b:0;}}}s:40:"Brick\\ORM\\Tests\\Resources\\Models\\Country";r:48;}');
