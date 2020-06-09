<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\DBAL\Driver\PDOOracle\Driver as PDOOracleDriver;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;

use function random_bytes;
use function str_replace;

class BinaryTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof PDOOracleDriver) {
            self::markTestSkipped('PDO_OCI doesn\'t support binding binary values');
        }

        $table = new Table('binary_table');
        $table->addColumn('id', 'binary', [
            'length' => 16,
            'fixed' => true,
        ]);
        $table->addColumn('val', 'binary', ['length' => 64]);
        $table->setPrimaryKey(['id']);

        $sm = $this->connection->getSchemaManager();
        $sm->dropAndCreateTable($table);
    }

    public function testInsertAndSelect(): void
    {
        $id1 = random_bytes(16);
        $id2 = random_bytes(16);

        $value1 = random_bytes(64);
        $value2 = random_bytes(64);

        /** @see https://bugs.php.net/bug.php?id=76322 */
        if ($this->connection->getDriver() instanceof DB2Driver) {
            $value1 = str_replace("\x00", "\xFF", $value1);
            $value2 = str_replace("\x00", "\xFF", $value2);
        }

        $this->insert($id1, $value1);
        $this->insert($id2, $value2);

        self::assertSame($value1, $this->select($id1));
        self::assertSame($value2, $this->select($id2));
    }

    private function insert(string $id, string $value): void
    {
        $result = $this->connection->insert('binary_table', [
            'id'  => $id,
            'val' => $value,
        ], [
            ParameterType::BINARY,
            ParameterType::BINARY,
        ]);

        self::assertSame(1, $result);
    }

    /**
     * @return mixed
     */
    private function select(string $id)
    {
        $value = $this->connection->fetchOne(
            'SELECT val FROM binary_table WHERE id = ?',
            [$id],
            [ParameterType::BINARY]
        );

        return Type::getType('binary')->convertToPHPValue($value, $this->connection->getDatabasePlatform());
    }
}
