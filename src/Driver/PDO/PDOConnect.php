<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use PDO;
use SensitiveParameter;

use const PHP_VERSION_ID;

/** @internal */
trait PDOConnect
{
    /** @param array<int, mixed> $options */
    private function doConnect(
        #[SensitiveParameter]
        string $dsn,
        string $username,
        #[SensitiveParameter]
        string $password,
        array $options
    ): PDO {
        if (PHP_VERSION_ID < 80400) {
            return new PDO($dsn, $username, $password, $options);
        }

        return PDO::connect($dsn, $username, $password, $options);
    }
}
