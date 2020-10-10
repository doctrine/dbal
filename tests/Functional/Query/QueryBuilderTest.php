<?php

namespace Doctrine\Tests\DBAL\Functional\Query;

use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

class QueryBuilderTest extends DbalFunctionalTestCase
{
    /** @var bool */
    private static $generated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$generated !== false) {
            return;
        }

        $table = new Table('lock_test_table');
        $table->addColumn('primary_key', 'integer');
        $table->addColumn('data', 'string');
        $table->setPrimaryKey(['primary_key']);

        $sm = $this->connection->getSchemaManager();
        $sm->createTable($table);

        $this->connection->insert('lock_test_table', [
            'primary_key' => 1,
            'data' => 'foo',
        ]);

        $this->connection->insert('lock_test_table', [
            'primary_key' => 2,
            'data' => 'bar',
        ]);

        $this->connection->insert('lock_test_table', [
            'primary_key' => 3,
            'data' => 'baz',
        ]);

        self::$generated = true;
    }

    public function testReadLock()
    {
        $sut = $this->connection->createQueryBuilder();
        $query = $sut->select('lt.*')
            ->from('lock_test_table', 'lt')
            ->setLockMode(LockMode::PESSIMISTIC_READ)
            ->where($sut->expr()->eq('lt.data', '?'))
            ->setParameter(0, 'foo', ParameterType::STRING);

        $result = $query->execute();
        $record = $result->fetchAllAssociative();
        self::assertEquals(1, $record[0]['primary_key']);
    }

    public function testWriteLock()
    {
        $sut = $this->connection->createQueryBuilder();
        $query = $sut->select('lt.*')
            ->from('lock_test_table')
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->where($sut->expr()->eq('lt.data', '?'))
            ->setParameter(0, 'bar', ParameterType::STRING);

        $result = $query->execute();
        $record = $result->fetchAllAssociative();
        self::assertEquals(2, $record[0]['primary_key']);
    }
}
