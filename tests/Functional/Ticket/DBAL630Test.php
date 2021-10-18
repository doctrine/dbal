<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PDO;

class DBAL630Test extends FunctionalTestCase
{
    private bool $running = false;

    protected function setUp(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('Currently restricted to PostgreSQL');
        }

        try {
            $this->connection->executeStatement('CREATE TABLE dbal630 (id SERIAL, bool_col BOOLEAN NOT NULL)');
            $this->connection->executeStatement('CREATE TABLE dbal630_allow_nulls (id SERIAL, bool_col BOOLEAN)');
        } catch (Exception $e) {
        }

        $this->running = true;
    }

    protected function tearDown(): void
    {
        if (! $this->running) {
            return;
        }

        $this->getWrappedConnection()
            ->getWrappedConnection()
            ->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function testBooleanConversionSqlLiteral(): void
    {
        $this->connection->executeStatement('INSERT INTO dbal630 (bool_col) VALUES(false)');
        $id = $this->connection->lastInsertId();
        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssociative('SELECT bool_col FROM dbal630 WHERE id = ?', [$id]);
        self::assertNotFalse($row);

        self::assertFalse($row['bool_col']);
    }

    public function testBooleanConversionBoolParamRealPrepares(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO dbal630 (bool_col) VALUES(?)',
            ['false'],
            [ParameterType::BOOLEAN]
        );
        $id = $this->connection->lastInsertId();
        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssociative('SELECT bool_col FROM dbal630 WHERE id = ?', [$id]);
        self::assertNotFalse($row);

        self::assertFalse($row['bool_col']);
    }

    public function testBooleanConversionBoolParamEmulatedPrepares(): void
    {
        $this->getWrappedConnection()
            ->getWrappedConnection()
            ->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $platform = $this->connection->getDatabasePlatform();

        $stmt = $this->connection->prepare('INSERT INTO dbal630 (bool_col) VALUES(?)');
        $stmt->bindValue(1, $platform->convertBooleansToDatabaseValue('false'), ParameterType::BOOLEAN);
        $stmt->executeStatement();

        $id = $this->connection->lastInsertId();

        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssociative('SELECT bool_col FROM dbal630 WHERE id = ?', [$id]);
        self::assertNotFalse($row);

        self::assertFalse($row['bool_col']);
    }

    /**
     * @dataProvider booleanTypeConversionWithoutPdoTypeProvider
     */
    public function testBooleanConversionNullParamEmulatedPrepares(
        ?bool $statementValue,
        ?bool $databaseConvertedValue
    ): void {
        $this->getWrappedConnection()
            ->getWrappedConnection()
            ->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $platform = $this->connection->getDatabasePlatform();

        $stmt = $this->connection->prepare('INSERT INTO dbal630_allow_nulls (bool_col) VALUES(?)');
        $stmt->bindValue(1, $platform->convertBooleansToDatabaseValue($statementValue));
        $stmt->executeStatement();

        $id = $this->connection->lastInsertId();

        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssociative('SELECT bool_col FROM dbal630_allow_nulls WHERE id = ?', [$id]);
        self::assertNotFalse($row);

        self::assertSame($databaseConvertedValue, $row['bool_col']);
    }

    /**
     * @dataProvider booleanTypeConversionUsingBooleanTypeProvider
     */
    public function testBooleanConversionNullParamEmulatedPreparesWithBooleanTypeInBindValue(
        ?bool $statementValue,
        bool $databaseConvertedValue
    ): void {
        $this->getWrappedConnection()
            ->getWrappedConnection()
            ->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $platform = $this->connection->getDatabasePlatform();

        $stmt = $this->connection->prepare('INSERT INTO dbal630_allow_nulls (bool_col) VALUES(?)');
        $stmt->bindValue(
            1,
            $platform->convertBooleansToDatabaseValue($statementValue),
            ParameterType::BOOLEAN
        );
        $stmt->executeStatement();

        $id = $this->connection->lastInsertId();

        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssociative('SELECT bool_col FROM dbal630_allow_nulls WHERE id = ?', [$id]);
        self::assertNotFalse($row);

        self::assertSame($databaseConvertedValue, $row['bool_col']);
    }

    /**
     * Boolean conversion mapping provider
     *
     * @return mixed[][]
     */
    public static function booleanTypeConversionUsingBooleanTypeProvider(): iterable
    {
        return [
            // statement value, database converted value result
            [true, true],
            [false, false],
            [null, false],
        ];
    }

    /**
     * Boolean conversion mapping provider
     *
     * @return mixed[][]
     */
    public static function booleanTypeConversionWithoutPdoTypeProvider(): iterable
    {
        return [
            // statement value, database converted value result
            [true, true],
            [false, false],
            [null, null],
        ];
    }

    private function getWrappedConnection(): Connection
    {
        $connection = $this->connection->getWrappedConnection();
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
