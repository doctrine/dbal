<?php
/*
 * This file bootstraps the test environment.
 */
namespace Doctrine\Tests;

error_reporting(E_ALL | E_STRICT);

require_once __DIR__ . '/../../../vendor/autoload.php';

$classLoader = new \Doctrine\Common\ClassLoader('Doctrine\Tests', __DIR__ . '/../../');
$classLoader->register();

