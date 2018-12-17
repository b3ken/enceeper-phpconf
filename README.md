# Enceeper PHP Conf

Is a PHP package used for fetching keys from a user's Enceeper account. The main goal is to be able to store configuration information in a key and have this information delivered to a PHP application (other uses are welcome 😀).

## Introduction

The Enceeper app (https://github.com/enceeper/enceeper) and the Enceeper service (https://www.enceeper.com/) can be used to securely store and retrieve credentials (usernames, passwords, API keys etc). We wanted to extend this idea also to configuration files: securely store and deliver configuration information for PHP projects (and in the future other programming languages). The scenario is that an Enceeper app user can store a key to his account with configuration information in JSON format. This package will retrieve the encrypted JSON and return the decrypted information to be used as configuration. We also added a filesystem and Redis caching mechanisms to better utilize resources and gracefully handle errors.

We believe this approach has the following benefits:

* Configuration for your projects is secury stored in Enceeper (encrypted)
* You can update this information in Enceeper and have it deployed to your project, without updating anything on the server or your application
* The information is provided encrypted and can be decrypted only with the correct password
* This solution has no additional access to your Enceeper account

> The Enceeper app encrypts all the information prior to delivering them to the Enceeper service. This package receives the encrypted information for an entry and performs a local decryption using an entry-specific decryption key. This is an additional layer of security on top of TLS (HTTPS) for the network traffic.

## Installation with Composer

```
{
    "require": {
        "enceeper/enceeper-phpconf": "^1.0"
    }
}
```

### Important notes

When using this package in production environments you have to take into account the following:

1. For simplicity we provide a PHP implementation for scrypt (using SHA512). This implementation has the following limitations:
  * It requires PHP 64-bit, since PHP does not support unsigned integers
  * It runs very slow (about 20 secs for N=32768)
  * It requires memory so you will need to edit php.ini and set the memory_limit directive (about 400MB for N=32768)
  * We highly recommend you use the following: https://github.com/enceeper/scrypt and provide the path to the executable.
2. We provide a caching mechanism utilizing the filesystem or Redis. You have to take into account the following:
  * For the Redis cache you need to have Redis installed (Redis server: https://redis.io and PHP Redis package: https://github.com/phpredis/phpredis)
  * For the filesystem cache you have to make sure that the script has write permissions to the target directory
  * For production systems it is recommended to utilize the batch mode strategy: `STRATEGY_BATCH_MODE`. You must also setup a job (i.e. crontab) to periodically update the cache contents
3. We also recommend you have the internationalization extension installed (intl) that provides the `Normalizer::normalize` method in order to retrieve the NFKD form of the password and have consistent results across platforms and encodings (http://php.net/manual/en/normalizer.normalize.php)

## Usage

```php
<?php

require __DIR__ .'/vendor/autoload.php';

// The key identifier and the password
$pw = 'ThisLittlePiggyWasHappyWithEncryption';
$id = 'fd925fdd94706d418cc3ddbe8bf46ce9';

// Connect to Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379, 2.0);

// Instanciate Enceeper and the cache
$enc = new Enceeper\Enceeper($pw, $id);
$cch = new Enceeper\RedisCache($redis, 'enceeper-conf', 5, $enc, Enceeper\AbstractCache::STRATEGY_BATCH_MODE);

// Get the configuration array
$conf = $cch->get();

//
// The code bellow will be called asynchronously by another script
//
//
//$cch->update();
//

//
// You can continue with your application logic
//

```

## Copyright and license

Copyright 2018 Vassilis Poursalidis. Released under GNU GPL3 or later - see the `LICENSE` file for details.
