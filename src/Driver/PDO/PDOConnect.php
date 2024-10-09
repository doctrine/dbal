<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use PDO;

use const PHP_VERSION_ID;

/** @internal */
trait PDOConnect
{
    /** @param array<int, mixed> $options */
    private function doConnect(
        string $dsn,
        string $username,
        string $password,
        array $options,
    ): PDO {
        // see https://github.com/php/php-src/issues/16314
        if (PHP_VERSION_ID < 80400 || ($options[PDO::ATTR_PERSISTENT] ?? false) === true) {
            return new PDO($dsn, $username, $password, $options);
        }

        return PDO::connect($dsn, $username, $password, $options);
    }
}
