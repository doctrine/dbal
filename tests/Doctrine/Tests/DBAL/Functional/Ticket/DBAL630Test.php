<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\ParameterType;
use Doctrine\Tests\DbalFunctionalTestCase;
use PDO;
use function in_array;

/**
 * @group DBAL-630
 */
class DBAL630Test extends DbalFunctionalTestCase
{
    /** @var bool */
    private $running = false;

    protected function setUp() : void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform()->getName();

        if (! in_array($platform, ['postgresql'])) {
            $this->markTestSkipped('Currently restricted to PostgreSQL');
        }

        try {
            $this->connection->exec('CREATE TABLE dbal630 (id SERIAL, bool_col BOOLEAN NOT NULL);');
            $this->connection->exec('CREATE TABLE dbal630_allow_nulls (id SERIAL, bool_col BOOLEAN);');
        } catch (DBALException $e) {
        }
        $this->running = true;
    }

    protected function tearDown() : void
    {
        if ($this->running) {
            $this->connection->getWrappedConnection()->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        parent::tearDown();
    }

    public function testBooleanConversionSqlLiteral() : void
    {
        $this->connection->executeUpdate('INSERT INTO dbal630 (bool_col) VALUES(false)');
        $id = $this->connection->lastInsertId('dbal630_id_seq');
        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssoc('SELECT bool_col FROM dbal630 WHERE id = ?', [$id]);

        self::assertFalse($row['bool_col']);
    }

    public function testBooleanConversionBoolParamRealPrepares() : void
    {
        $this->connection->executeUpdate(
            'INSERT INTO dbal630 (bool_col) VALUES(?)',
            ['false'],
            [ParameterType::BOOLEAN]
        );
        $id = $this->connection->lastInsertId('dbal630_id_seq');
        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssoc('SELECT bool_col FROM dbal630 WHERE id = ?', [$id]);

        self::assertFalse($row['bool_col']);
    }

    public function testBooleanConversionBoolParamEmulatedPrepares() : void
    {
        $this->connection->getWrappedConnection()->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $platform = $this->connection->getDatabasePlatform();

        $stmt = $this->connection->prepare('INSERT INTO dbal630 (bool_col) VALUES(?)');
        $stmt->bindValue(1, $platform->convertBooleansToDatabaseValue('false'), ParameterType::BOOLEAN);
        $stmt->execute();

        $id = $this->connection->lastInsertId('dbal630_id_seq');

        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssoc('SELECT bool_col FROM dbal630 WHERE id = ?', [$id]);

        self::assertFalse($row['bool_col']);
    }

    /**
     * @dataProvider booleanTypeConversionWithoutPdoTypeProvider
     */
    public function testBooleanConversionNullParamEmulatedPrepares(
        ?bool $statementValue,
        ?bool $databaseConvertedValue
    ) : void {
        $this->connection->getWrappedConnection()->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $platform = $this->connection->getDatabasePlatform();

        $stmt = $this->connection->prepare('INSERT INTO dbal630_allow_nulls (bool_col) VALUES(?)');
        $stmt->bindValue(1, $platform->convertBooleansToDatabaseValue($statementValue));
        $stmt->execute();

        $id = $this->connection->lastInsertId('dbal630_allow_nulls_id_seq');

        self::assertNotEmpty($id);

        $row = $this->connection->fetchAssoc('SELECT bool_col FROM dbal630_allow_nulls WHERE id = ?', [$id]);

        self::assertSame($databaseConvertedValue, $row['bool_col']);
    }

    /**
     * @dataProvider booleanTypeConversionUsingBooleanTypeProvider
     */
    public function testBooleanConversionNullParamEmulatedPreparesWithBooleanTypeInBindValue(
        ?bool $statementValue,
        bool $databaseConvertedValue
    ) : void {
        $this->connection->getWrappedConnection()->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

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

        $row = $this->connection->fetchAssoc('SELECT bool_col FROM dbal630_allow_nulls WHERE id = ?', [$id]);

        self::assertSame($databaseConvertedValue, $row['bool_col']);
    }

    /**
     * Boolean conversion mapping provider
     *
     * @return mixed[][]
     */
    public static function booleanTypeConversionUsingBooleanTypeProvider() : iterable
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
    public static function booleanTypeConversionWithoutPdoTypeProvider() : iterable
    {
        return [
            // statement value, database converted value result
            [true, true],
            [false, false],
            [null, null],
        ];
    }
}
