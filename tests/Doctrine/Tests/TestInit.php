<?php
/*
 * This file bootstraps the test environment.
 */
namespace Doctrine\Tests;

error_reporting(E_ALL | E_STRICT);

$autoloader = require_once __DIR__ . '/../../../vendor/autoload.php';
$autoloader->add('Doctrine\Tests\\', __DIR__ . '/../../', true);
