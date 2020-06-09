<?php

declare(strict_types=1);

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
                [1017],
                [12545],
            ],
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => [
                [2292],
            ],
            self::EXCEPTION_INVALID_FIELD_NAME => [
                [904],
            ],
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => [
                [918],
                [960],
            ],
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => [
                [1400],
            ],
            self::EXCEPTION_SYNTAX_ERROR => [
                [923],
            ],
            self::EXCEPTION_TABLE_EXISTS => [
                [955],
            ],
            self::EXCEPTION_TABLE_NOT_FOUND => [
                [942],
            ],
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => [
                [1],
                [2299],
                [38911],
            ],
        ];
    }
}
