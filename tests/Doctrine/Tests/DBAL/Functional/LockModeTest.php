<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\Tests\DbalTestCase;
use Doctrine\Tests\TestUtil;

use function array_merge;
use function usort;

class LockModeTest extends DbalTestCase
{
    /** @var Connection */
    private $c1;

    /** @var Connection */
    private $c2;

    /** @var LockModeSQLLogger */
    private $c1Logger;

    /** @var LockModeSQLLogger */
    private $c2Logger;

    public function setUp(): void
    {
        $this->c1 = TestUtil::getConnection();
        $this->c2 = TestUtil::getConnection();

        $this->c1->getConfiguration()->setSQLLogger($this->c1Logger = new LockModeSQLLogger(1));
        $this->c2->getConfiguration()->setSQLLogger($this->c2Logger = new LockModeSQLLogger(2));

        $table = new Table('users');
        $table->addColumn('id', 'integer');

        $this->c1->getSchemaManager()->createTable($table);

        if ($this->c2->getSchemaManager()->tablesExist('users')) {
            return;
        }

        if ($this->c2->getDatabasePlatform() instanceof SqlitePlatform) {
            self::markTestSkipped('This test cannot run on SQLite using an in-memory database');
        }

        self::fail('Separate connections do not seem to talk to the same database');
    }

    public function tearDown(): void
    {
        $this->c1->getSchemaManager()->dropTable('users');

        $this->c1->close();
        $this->c2->close();

        $queries = array_merge(
            $this->c1Logger->getQueries(),
            $this->c2Logger->getQueries()
        );

        usort($queries, static function (array $a, array $b) {
            return (int) (1000000 * ($a['time'] - $b['time']));
        });

        echo "\n";

        foreach ($queries as $query) {
            echo 'C' . $query['cid'] . ': ' . $query['sql'] . "\n";
        }
    }

    public function testLockModeNoneDoesNotBreakTransactionIsolation(): void
    {
        try {
            $this->c1->setTransactionIsolation(TransactionIsolationLevel::READ_COMMITTED);
            $this->c2->setTransactionIsolation(TransactionIsolationLevel::READ_COMMITTED);
        } catch (Exception $e) {
            self::markTestSkipped('This test must be able to set a transaction isolation level');
        }

        $this->c1->beginTransaction();
        $this->c2->beginTransaction();

        $this->c1->insert('users', ['id' => 1]);

        $query = 'SELECT id FROM users';
        $query = $this->c2->getDatabasePlatform()->appendLockHint($query, LockMode::NONE);

        self::assertFalse($this->c2->fetchOne($query));

        $this->c1->commit();
        $this->c2->commit();
    }
}
