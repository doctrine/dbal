<?php

declare(strict_types=1);

// PHPStan does not read global constants from the stubs yet, remove this when it does
if (defined('OCI_NO_AUTO_COMMIT')) {
    return;
}

define('OCI_NO_AUTO_COMMIT', 0);
