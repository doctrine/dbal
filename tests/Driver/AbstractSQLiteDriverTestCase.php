<?php

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\SQLite;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SqliteSchemaManager;

/** @extends AbstractDriverTestCase<SqlitePlatform> */
abstract class AbstractSQLiteDriverTestCase extends AbstractDriverTestCase
{
    protected function createPlatform(): AbstractPlatform
    {
        return new SqlitePlatform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new SqliteSchemaManager(
            $connection,
            $this->createPlatform(),
        );
    }

    protected function createExceptionConverter(): ExceptionConverter
    {
        return new SQLite\ExceptionConverter();
    }
}
