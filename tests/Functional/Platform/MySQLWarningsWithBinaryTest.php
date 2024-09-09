<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use PDO;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

use function assert;
use function hex2bin;

/** @psalm-import-type WrapperParameterTypeArray from Connection */
final class MySQLWarningsWithBinaryTest extends FunctionalTestCase
{
    #[Before]
    public function createTable(): void
    {
        if (! TestUtil::isDriverOneOf('pdo_mysql')) {
            self::markTestSkipped('This is only relevant when using PDO with emulated prepared statemes');
        }

        $table = new Table('binary_warnings_test');
        $table->addColumn('id', 'binary', ['notnull' => false, 'length' => 16]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    /** @psalm-param WrapperParameterTypeArray $types */
    #[Test]
    #[DataProvider('useCases')]
    public function binaryValuesDoNotEmitWarningsWithEmulation(string $query, array $types): void
    {
        $this->connection->executeStatement($query, [hex2bin('0191d886e6dc73e7af1fee7f99ec6235')], $types);

        self::assertSame([], $this->connection->executeQuery('SHOW WARNINGS')->fetchAllAssociative());
    }

    /** @psalm-param WrapperParameterTypeArray $types */
    #[Test]
    #[DataProvider('useCases')]
    public function binaryValuesDoNotEmitWarningsWithoutEmulation(string $query, array $types): void
    {
        $pdo = $this->connection->getNativeConnection();
        assert($pdo instanceof PDO);

        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->connection->executeStatement($query, [hex2bin('0191d886e6dc73e7af1fee7f99ec6235')], $types);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        self::assertSame([], $this->connection->executeQuery('SHOW WARNINGS')->fetchAllAssociative());
    }

    /** @return iterable<array{string, WrapperParameterTypeArray}> */
    public static function useCases(): iterable
    {
        yield '_binary and ParameterType::BINARY' => [
            'INSERT INTO `binary_warnings_test` (`id`) VALUES (_binary ?)',
            [ParameterType::BINARY],
        ];

        yield '_binary and ParameterType::STRING' => [
            'INSERT INTO `binary_warnings_test` (`id`) VALUES (_binary ?)',
            [],
        ];

        yield 'string and ParameterType::BINARY' => [
            'INSERT INTO `binary_warnings_test` (`id`) VALUES (?)',
            [ParameterType::BINARY],
        ];

        yield 'string and ParameterType::STRING' => [
            'INSERT INTO `binary_warnings_test` (`id`) VALUES (?)',
            [],
        ];
    }
}
