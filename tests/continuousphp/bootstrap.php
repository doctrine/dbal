<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;

(static function (): void {
    // workaround for https://bugs.php.net/bug.php?id=77120
    DriverManager::getConnection([
        'driver' => 'oci8',
        'host' => 'oracle-xe-11',
        'user' => 'ORACLE',
        'password' => 'ORACLE',
        'dbname' => 'XE',
    ])->query('ALTER USER ORACLE IDENTIFIED BY ORACLE');
})();
