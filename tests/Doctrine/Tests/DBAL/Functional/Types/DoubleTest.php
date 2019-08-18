<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use function abs;
use function floatval;
use function is_int;
use function is_string;
use function microtime;
use function sprintf;

class DoubleTest extends DbalFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $table = new Table('double_table');
        $table->addColumn('id', 'integer');
        $table->addColumn('val', 'float');
        $table->setPrimaryKey(['id']);

        $sm = $this->connection->getSchemaManager();
        $sm->dropAndCreateTable($table);
    }

    public function testInsertAndSelect() : void
    {
        $value1 = 1.1;
        $value2 = 77.99999999999;
        $value3 = microtime(true);

        $this->insert(1, $value1);
        $this->insert(2, $value2);
        $this->insert(3, $value3);

        $result1 = $this->select(1);
        $result2 = $this->select(2);
        $result3 = $this->select(3);

        if (is_string($result1)) {
            $result1 = floatval($result1);
            $result2 = floatval($result2);
            $result3 = floatval($result3);
        }

        if ($result1 === false) {
            $this->fail('Expected $result1 to not be false');

            return;
        }
        if ($result2 === false) {
            $this->fail('Expected $result2 to not be false');

            return;
        }
        if ($result3 === false) {
            $this->fail('Expected $result3 to not be false');

            return;
        }

        $diff1 = abs($result1 - $value1);
        $diff2 = abs($result2 - $value2);
        $diff3 = abs($result3 - $value3);

        $this->assertLessThanOrEqual(0.0001, $diff1, sprintf('%f, %f, %f', $diff1, $result1, $value1));
        $this->assertLessThanOrEqual(0.0001, $diff2, sprintf('%f, %f, %f', $diff2, $result2, $value2));
        $this->assertLessThanOrEqual(0.0001, $diff3, sprintf('%f, %f, %f', $diff3, $result3, $value3));

        $result1 = $this->selectDouble($value1);
        $result2 = $this->selectDouble($value2);
        $result3 = $this->selectDouble($value3);

        $this->assertSame(is_int($result1) ? 1 : '1', $result1);
        $this->assertSame(is_int($result2) ? 2 : '2', $result2);
        $this->assertSame(is_int($result3) ? 3 : '3', $result3);
    }

    private function insert(int $id, float $value) : void
    {
        $result = $this->connection->insert('double_table', [
            'id'  => $id,
            'val' => $value,
        ], [
            ParameterType::INTEGER,
            ParameterType::DOUBLE,
        ]);

        self::assertSame(1, $result);
    }

    /**
     * @return mixed
     */
    private function select(int $id)
    {
        return $this->connection->fetchColumn(
            'SELECT val FROM double_table WHERE id = ?',
            [$id],
            0,
            [ParameterType::INTEGER]
        );
    }

    /**
     * @return mixed
     */
    private function selectDouble(float $value)
    {
        return $this->connection->fetchColumn(
            'SELECT id FROM double_table WHERE val = ?',
            [$value],
            0,
            [ParameterType::DOUBLE]
        );
    }
}
