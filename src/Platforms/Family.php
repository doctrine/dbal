<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

interface Family
{
    public const DB2        = 'db2';
    public const MSSQL      = 'mssql';
    public const MYSQL      = 'mysql';
    public const ORACLE     = 'oracle';
    public const POSTGRESQL = 'postgresql';
    public const SQLITE     = 'sqlite';
}
