<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;

return Architecture::define()
    ->withPreset(Preset::PSR4())
    ->layer('ArrayParameters', [
        'src/ArrayParameters/',
        'src/ExpandArrayParameters.php',
    ])
    ->layer('Cache', 'src/Cache/')
    ->layer('Configuration', 'src/Configuration.php')
    ->layer('Connection', [
        'src/Connection.php',
        'src/Connection/',
        'src/Connections/',
        'src/Result.php',
        'src/Statement.php',
    ])
    ->layer('Driver', [
        'src/Driver.php',
        'src/Driver/',
        'src/ServerVersionProvider.php',
    ])
    ->layer('DriverManager', 'src/DriverManager.php')
    ->layer('Exception', [
        'src/Exception.php',
        'src/ConnectionException.php',
        'src/Exception/',
    ])
    ->layer('Logging', 'src/Logging/')
    ->layer('Parameters', [
        'src/ArrayParameterType.php',
        'src/ParameterType.php',
    ])
    ->layer('Platforms', [
        'src/Platforms/',
        'src/LockMode.php',
        'src/TransactionIsolationLevel.php',
    ])
    ->layer('Portability', [
        'src/Portability/',
        'src/ColumnCase.php',
    ])
    ->layer('Query', [
        'src/Query.php',
        'src/Query/',
    ])
    ->layer('Schema', 'src/Schema/')
    ->layer('SQL', 'src/SQL/')
    ->layer('Tools', 'src/Tools/')
    ->layer('Types', 'src/Types/')
    ->ruleset([
        'ArrayParameters' => ['Parameters', 'SQL', 'Types'],
        'Cache'           => ['Connection', 'Driver', 'Exception'],
        'Configuration'   => ['Driver', 'Exception', 'Schema'],
        'Connection'      => ['+ArrayParameters', 'Cache', '+Configuration', '+Driver', 'DriverManager'],
        'Driver'          => ['Exception', 'Parameters', 'Platforms', 'Query', 'SQL'],
        'DriverManager'   => ['Configuration', 'Connection', 'Driver', 'Exception'],
        'Exception'       => ['Connection', 'Driver', 'Platforms', 'Query', 'SQL'],
        'Logging'         => ['Driver', 'Parameters', 'Platforms'],
        'Parameters'      => [],
        'Platforms'       => ['Query', '+Schema'],
        'Portability'     => ['Driver', 'Parameters', 'Platforms'],
        'Query'           => ['Cache', 'Connection', 'Exception', 'Parameters', 'Types'],
        'Schema'          => ['Connection', 'Exception', 'Platforms', 'SQL', 'Types'],
        'SQL'             => ['Exception', 'Platforms', 'Query', 'Schema'],
        'Tools'           => ['Connection', 'Driver', 'DriverManager', 'Exception'],
        'Types'           => ['Exception', 'Parameters', 'Platforms'],
    ]);
