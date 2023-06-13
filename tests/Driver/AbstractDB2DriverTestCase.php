<?php

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\IBMDB2\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2111Platform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\DB2SchemaManager;

/** @extends AbstractDriverTestCase<DB2Platform> */
abstract class AbstractDB2DriverTestCase extends AbstractDriverTestCase
{
    protected function createPlatform(): AbstractPlatform
    {
        return new DB2Platform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new DB2SchemaManager(
            $connection,
            $this->createPlatform(),
        );
    }

    protected function createExceptionConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabasePlatformsForVersions(): array
    {
        return [
            ['10.1.0', DB2Platform::class, 'https://github.com/doctrine/dbal/pull/5156', true],
            ['10.1.0.0', DB2Platform::class, 'https://github.com/doctrine/dbal/pull/5156', true],
            ['DB2/LINUXX8664 10.1.0.0', DB2Platform::class, 'https://github.com/doctrine/dbal/pull/5156', true],
            ['11.1.0', DB2111Platform::class],
            ['11.1.0.0', DB2111Platform::class],
            ['DB2/LINUXX8664 11.1.0.0', DB2111Platform::class],
            ['11.5.8', DB2111Platform::class],
            ['11.5.8.0', DB2111Platform::class],
            ['DB2/LINUXX8664 11.5.8.0', DB2111Platform::class],
        ];
    }
}
