<?php

declare(strict_types=1);

(function () : void {
    $pos = array_search('--coverage-clover', $_SERVER['argv'], true);

    if ($pos === false) {
        return;
    }

    $file = $_SERVER['argv'][$pos + 1];

    register_shutdown_function(function () use ($file) : void {
        $cmd = 'wget https://github.com/scrutinizer-ci/ocular/releases/download/1.5.2/ocular.phar'
            . ' && php ocular.phar code-coverage:upload --format=php-clover ' . escapeshellarg($file);

        passthru($cmd);
    });
})();
