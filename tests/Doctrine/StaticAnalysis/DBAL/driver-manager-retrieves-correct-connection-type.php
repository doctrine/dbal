<?php

declare(strict_types=1);

namespace Doctrine\StaticAnalysis\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class MyConnection extends Connection
{
}

function makeMeACustomConnection(): MyConnection
{
    return DriverManager::getConnection([
        'wrapperClass' => MyConnection::class,
    ]);
}
