<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PDO;

use function assert;

/**
 * @group DBAL-630
 */
class DBAL630Test extends FunctionalTestCase
{
    /** @var bool */
    private $running = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->connection->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            self::markTestSkipped('Currently restricted to PostgreSQL');
        }

        try {
            $this->connection->exec('CREATE TABLE dbal630 (id SERIAL, bool_col BOOLEAN NOT NULL);');
            $this->connection->exec('CREATE TABLE dbal630_allow_nulls (id SERIAL, bool_col BOOLEAN);');
        } catch (DBALException $e) {
        }

        $this->running = true;
    }

    protected function tearDown(): void
    {
        if ($this->running) {
            $pdo = $this->getPDO();

            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        parent::tearDown();
    }

    public function testBooleanConversionSqlLiteral(): void
    {
        $this->connection->executeUpdate('INSERT INTO dbal630 (bool_col) VALUES(false)');
        $id = $this->connection->lastInsertId('dbal630_id_seq');
        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssociative('SELECT bool_col FROM dbal630 WHERE id = ?', [$id]);

        self::assertIsArray($row);
        self::assertFalse($row['bool_col']);
    }

    public function testBooleanConversionBoolParamRealPrepares(): void
    {
        $this->connection->executeUpdate(
            'INSERT INTO dbal630 (bool_col) VALUES(?)',
            ['false'],
            [ParameterType::BOOLEAN]
        );
        $id = $this->connection->lastInsertId('dbal630_id_seq');
        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssociative('SELECT bool_col FROM dbal630 WHERE id = ?', [$id]);

        self::assertIsArray($row);
        self::assertFalse($row['bool_col']);
    }

    public function testBooleanConversionBoolParamEmulatedPrepares(): void
    {
        $pdo = $this->getPDO();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $platform = $this->connection->getDatabasePlatform();

        $stmt = $this->connection->prepare('INSERT INTO dbal630 (bool_col) VALUES(?)');
        $stmt->bindValue(1, $platform->convertBooleansToDatabaseValue('false'), ParameterType::BOOLEAN);
        $stmt->execute();

        $id = $this->connection->lastInsertId('dbal630_id_seq');

        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssociative('SELECT bool_col FROM dbal630 WHERE id = ?', [$id]);

        self::assertIsArray($row);
        self::assertFalse($row['bool_col']);
    }

    /**
     * @dataProvider booleanTypeConversionWithoutPdoTypeProvider
     */
    public function testBooleanConversionNullParamEmulatedPrepares(
        ?bool $statementValue,
        ?bool $databaseConvertedValue
    ): void {
        $pdo = $this->getPDO();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $platform = $this->connection->getDatabasePlatform();

        $stmt = $this->connection->prepare('INSERT INTO dbal630_allow_nulls (bool_col) VALUES(?)');
        $stmt->bindValue(1, $platform->convertBooleansToDatabaseValue($statementValue));
        $stmt->execute();

        $id = $this->connection->lastInsertId('dbal630_allow_nulls_id_seq');

        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssociative('SELECT bool_col FROM dbal630_allow_nulls WHERE id = ?', [$id]);

        self::assertIsArray($row);
        self::assertSame($databaseConvertedValue, $row['bool_col']);
    }

    /**
     * @dataProvider booleanTypeConversionUsingBooleanTypeProvider
     */
    public function testBooleanConversionNullParamEmulatedPreparesWithBooleanTypeInBindValue(
        ?bool $statementValue,
        bool $databaseConvertedValue
    ): void {
        $pdo = $this->getPDO();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $platform = $this->connection->getDatabasePlatform();

        $stmt = $this->connection->prepare('INSERT INTO dbal630_allow_nulls (bool_col) VALUES(?)');
        $stmt->bindValue(
            1,
            $platform->convertBooleansToDatabaseValue($statementValue),
            ParameterType::BOOLEAN
        );
        $stmt->execute();

        $id = $this->connection->lastInsertId('dbal630_allow_nulls_id_seq');

        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssociative('SELECT bool_col FROM dbal630_allow_nulls WHERE id = ?', [$id]);

        self::assertIsArray($row);
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

    private function getPDO(): PDO
    {
        $wrappedConnection = $this->connection->getWrappedConnection();
        assert($wrappedConnection instanceof PDOConnection);

        return $wrappedConnection->getWrappedConnection();
    }
}
