<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\DBALException;

final class InvalidPdoInstance extends DBALException
{
    public static function new() : self
    {
        return new self("The 'pdo' option was used in DriverManager::getConnection() but no instance of PDO was given.");
    }
}
