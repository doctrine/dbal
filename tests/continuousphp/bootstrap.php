<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;

(static function () : void {
    // workaround for https://bugs.php.net/bug.php?id=77120
    DriverManager::getConnection([
        'driver' => 'oci8',
        'host' => 'oracle-xe-11',
        'user' => 'ORACLE',
        'password' => 'ORACLE',
        'dbname' => 'XE',
    ])->query('ALTER USER ORACLE IDENTIFIED BY ORACLE');

    $pos = array_search('--coverage-clover', $_SERVER['argv'], true);

    if ($pos === false) {
        return;
    }

    assert(is_int($pos));

    $file = $_SERVER['argv'][$pos + 1];

    register_shutdown_function(static function () use ($file) : void {
        $cmd = 'wget https://github.com/php-coveralls/php-coveralls/releases/latest/download/php-coveralls.phar'
            . ' && php php-coveralls.phar -v -x' . escapeshellarg($file) . ' -o ' . escapeshellarg(dirname($file));

        passthru($cmd);
    });
})();
