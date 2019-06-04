<?php

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractOracleDriver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\OracleSchemaManager;

class AbstractOracleDriverTest extends AbstractDriverTest
{
    protected function createDriver(): Driver
    {
        return $this->getMockForAbstractClass(AbstractOracleDriver::class);
    }

    protected function createPlatform(): AbstractPlatform
    {
        return new OraclePlatform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new OracleSchemaManager($connection);
    }

    /**
     * {@inheritDoc}
     */
    protected static function getExceptionConversionData(): array
    {
        return [
            self::EXCEPTION_CONNECTION => [
                ['1017', null, null],
                ['12545', null, null],
            ],
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => [
                ['2292', null, null],
            ],
            self::EXCEPTION_INVALID_FIELD_NAME => [
                ['904', null, null],
            ],
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => [
                ['918', null, null],
                ['960', null, null],
            ],
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => [
                ['1400', null, null],
            ],
            self::EXCEPTION_SYNTAX_ERROR => [
                ['923', null, null],
            ],
            self::EXCEPTION_TABLE_EXISTS => [
                ['955', null, null],
            ],
            self::EXCEPTION_TABLE_NOT_FOUND => [
                ['942', null, null],
            ],
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => [
                ['1', null, null],
                ['2299', null, null],
                ['38911', null, null],
            ],
        ];
    }
}
