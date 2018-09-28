<?php

declare(strict_types=1);

(static function () : void {
    foreach (['ibm_db2', 'mysqli', 'oci8', 'sqlsrv', 'pgsql'] as $extension) {
        if (extension_loaded($extension)) {
            continue;
        }

        require sprintf(__DIR__ . '/../vendor/jetbrains/phpstorm-stubs/%1$s/%1$s.php', $extension);
    }
})();
