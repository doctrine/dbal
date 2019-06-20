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

    $file = $_SERVER['argv'][$pos + 1];

    register_shutdown_function(static function () use ($file) : void {
        $cmd = 'wget https://github.com/scrutinizer-ci/ocular/releases/download/1.5.2/ocular.phar'
            . ' && php ocular.phar code-coverage:upload --format=php-clover ' . escapeshellarg($file);

        passthru($cmd);
    });
})();
