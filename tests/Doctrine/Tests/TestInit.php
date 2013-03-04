<?php
/*
 * This file bootstraps the test environment.
 */
namespace Doctrine\Tests;

error_reporting(E_ALL | E_STRICT);

if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    $loader = require_once __DIR__ . '/../../../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../../../autoload.php')) {
    $loader = require __DIR__ . '/../../../vendor/autoload.php';
} else {
    throw new \RuntimeException('Could not locate composer autoloader');
}

$loader->add('Doctrine\Tests', __DIR__ . '/../../');
