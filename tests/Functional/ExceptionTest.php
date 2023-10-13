<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use Throwable;

use function array_merge;
use function chmod;
use function exec;
use function file_exists;
use function posix_geteuid;
use function posix_getpwuid;
use function sprintf;
use function sys_get_temp_dir;
use function touch;
use function unlink;

use const E_ALL;
use const E_WARNING;
use const PHP_OS_FAMILY;

/** @psalm-import-type Params from DriverManager */
class ExceptionTest extends FunctionalTestCase
{
    public function testPrimaryConstraintViolationException(): void
    {
        $table = new Table('duplicatekey_table');
        $table->addColumn('id', Types::INTEGER, []);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->connection->insert('duplicatekey_table', ['id' => 1]);

        $this->expectException(Exception\UniqueConstraintViolationException::class);
        $this->connection->insert('duplicatekey_table', ['id' => 1]);
    }

    public function testTableNotFoundException(): void
    {
        $sql = 'SELECT * FROM unknown_table';

        $this->expectException(Exception\TableNotFoundException::class);
        $this->connection->executeQuery($sql);
    }

    public function testTableExistsException(): void
    {
        $schemaManager = $this->connection->getSchemaManager();
        $table         = new Table('alreadyexist_table');
        $table->addColumn('id', Types::INTEGER, []);
        $table->setPrimaryKey(['id']);

        $this->expectException(Exception\TableExistsException::class);
        $schemaManager->createTable($table);
        $schemaManager->createTable($table);
    }

    public function testForeignKeyConstraintViolationExceptionOnInsert(): void
    {
        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->connection->insert('constraint_error_table', ['id' => 1]);
            $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->connection->insert('owning_table', ['id' => 2, 'constraint_id' => 2]);
        } catch (Exception\ForeignKeyConstraintViolationException $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testForeignKeyConstraintViolationExceptionOnUpdate(): void
    {
        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->connection->insert('constraint_error_table', ['id' => 1]);
            $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->connection->update('constraint_error_table', ['id' => 2], ['id' => 1]);
        } catch (Exception\ForeignKeyConstraintViolationException $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testForeignKeyConstraintViolationExceptionOnDelete(): void
    {
        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->connection->insert('constraint_error_table', ['id' => 1]);
            $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->connection->delete('constraint_error_table', ['id' => 1]);
        } catch (Exception\ForeignKeyConstraintViolationException $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testForeignKeyConstraintViolationExceptionOnTruncate(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->connection->insert('constraint_error_table', ['id' => 1]);
            $this->connection->insert('owning_table', ['id' => 1, 'constraint_id' => 1]);
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->connection->executeStatement($platform->getTruncateTableSQL('constraint_error_table'));
        } catch (Exception\ForeignKeyConstraintViolationException $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testNotNullConstraintViolationException(): void
    {
        $table = new Table('notnull_table');
        $table->addColumn('id', Types::INTEGER, []);
        $table->addColumn('val', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->expectException(Exception\NotNullConstraintViolationException::class);
        $this->connection->insert('notnull_table', ['id' => 1, 'val' => null]);
    }

    public function testInvalidFieldNameException(): void
    {
        $table = new Table('bad_columnname_table');
        $table->addColumn('id', Types::INTEGER, []);
        $this->dropAndCreateTable($table);

        // prevent the PHPUnit error handler from handling the warning that db2_bind_param() may trigger
        $this->iniSet('error_reporting', (string) (E_ALL & ~E_WARNING));

        $this->expectException(Exception\InvalidFieldNameException::class);
        $this->connection->insert('bad_columnname_table', ['name' => 5]);
    }

    public function testNonUniqueFieldNameException(): void
    {
        $table1 = new Table('ambiguous_list_table_1');
        $table1->addColumn('id', Types::INTEGER);
        $this->dropAndCreateTable($table1);

        $table2 = new Table('ambiguous_list_table_2');
        $table2->addColumn('id', Types::INTEGER);
        $this->dropAndCreateTable($table2);

        $sql = 'SELECT id FROM ambiguous_list_table_1, ambiguous_list_table_2';
        $this->expectException(Exception\NonUniqueFieldNameException::class);
        $this->connection->executeQuery($sql);
    }

    public function testUniqueConstraintViolationException(): void
    {
        $table = new Table('unique_column_table');
        $table->addColumn('id', Types::INTEGER);
        $table->addUniqueIndex(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('unique_column_table', ['id' => 5]);
        $this->expectException(Exception\UniqueConstraintViolationException::class);
        $this->connection->insert('unique_column_table', ['id' => 5]);
    }

    public function testSyntaxErrorException(): void
    {
        $table = new Table('syntax_error_table');
        $table->addColumn('id', Types::INTEGER, []);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $sql = 'SELECT id FRO syntax_error_table';
        $this->expectException(Exception\SyntaxErrorException::class);
        $this->connection->executeQuery($sql);
    }

    public function testConnectionExceptionSqLite(): void
    {
        if (! ($this->connection->getDatabasePlatform() instanceof SqlitePlatform)) {
            self::markTestSkipped('Only fails this way on sqlite');
        }

        // mode 0 is considered read-only on Windows
        $mode = PHP_OS_FAMILY !== 'Windows' ? 0444 : 0000;

        $filename = sprintf('%s/%s', sys_get_temp_dir(), 'doctrine_failed_connection_' . $mode . '.db');

        if (file_exists($filename)) {
            $this->cleanupReadOnlyFile($filename);
        }

        touch($filename);
        chmod($filename, $mode);

        if ($this->isLinuxRoot()) {
            exec(sprintf('chattr +i %s', $filename));
        }

        $params = [
            'driver' => 'pdo_sqlite',
            'path'   => $filename,
        ];
        $conn   = DriverManager::getConnection($params);

        $schema = new Schema();
        $table  = $schema->createTable('no_connection');
        $table->addColumn('id', Types::INTEGER);

        $this->expectException(Exception\ReadOnlyException::class);
        $this->expectExceptionMessage(
            'An exception occurred while executing a query: SQLSTATE[HY000]: ' .
            'General error: 8 attempt to write a readonly database',
        );

        try {
            foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
                $conn->executeStatement($sql);
            }
        } finally {
            $this->cleanupReadOnlyFile($filename);
        }
    }

    public function testInvalidUserName(): void
    {
        $this->testConnectionException(['user' => 'not_existing']);
    }

    public function testInvalidPassword(): void
    {
        $this->testConnectionException(['password' => 'really_not']);
    }

    public function testInvalidHost(): void
    {
        if (TestUtil::isDriverOneOf('pdo_sqlsrv', 'sqlsrv')) {
            self::markTestSkipped(
                'Some sqlsrv and pdo_sqlsrv versions do not provide the exception code or SQLSTATE for login timeout',
            );
        }

        $this->testConnectionException(['host' => 'localnope']);
    }

    /**
     * @param array<string, mixed> $params
     * @psalm-param Params $params
     *
     * @dataProvider getConnectionParams
     */
    private function testConnectionException(array $params): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform) {
            self::markTestSkipped('The SQLite driver does not use a network connection');
        }

        $params = array_merge(TestUtil::getConnectionParams(), $params);
        $conn   = DriverManager::getConnection($params);

        $this->expectException(Exception\ConnectionException::class);
        $conn->connect();
    }

    /** @return array<int, array<int, mixed>> */
    public static function getConnectionParams(): iterable
    {
        return [
            [['user' => 'not_existing']],
            [['password' => 'really_not']],
            [['host' => 'localnope']],
        ];
    }

    private function setUpForeignKeyConstraintViolationExceptionTest(): void
    {
        $schemaManager = $this->connection->getSchemaManager();

        $table = new Table('constraint_error_table');
        $table->addColumn('id', Types::INTEGER, []);
        $table->setPrimaryKey(['id']);

        $owningTable = new Table('owning_table');
        $owningTable->addColumn('id', Types::INTEGER, []);
        $owningTable->addColumn('constraint_id', Types::INTEGER, []);
        $owningTable->setPrimaryKey(['id']);
        $owningTable->addForeignKeyConstraint($table, ['constraint_id'], ['id']);

        $schemaManager->createTable($table);
        $schemaManager->createTable($owningTable);
    }

    private function tearDownForeignKeyConstraintViolationExceptionTest(): void
    {
        $schemaManager = $this->connection->getSchemaManager();

        $schemaManager->dropTable('owning_table');
        $schemaManager->dropTable('constraint_error_table');
    }

    private function isLinuxRoot(): bool
    {
        return PHP_OS_FAMILY !== 'Windows' && posix_getpwuid(posix_geteuid())['name'] === 'root';
    }

    private function cleanupReadOnlyFile(string $filename): void
    {
        if ($this->isLinuxRoot()) {
            exec(sprintf('chattr -i %s', $filename));
        }

        chmod($filename, 0200); // make the file writable again, so it can be removed on Windows
        unlink($filename);
    }
}
