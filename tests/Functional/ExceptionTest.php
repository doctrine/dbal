<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function array_merge;
use function assert;
use function chmod;
use function exec;
use function file_exists;
use function posix_geteuid;
use function posix_getpwuid;
use function sprintf;
use function sys_get_temp_dir;
use function touch;
use function unlink;
use function version_compare;

use const PHP_OS;

class ExceptionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof ExceptionConverterDriver) {
            return;
        }

        self::markTestSkipped('Driver does not support special exception handling.');
    }

    public function testPrimaryConstraintViolationException(): void
    {
        $table = new Table('duplicatekey_table');
        $table->addColumn('id', 'integer', []);
        $table->setPrimaryKey(['id']);

        $this->connection->getSchemaManager()->createTable($table);

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
        $table->addColumn('id', 'integer', []);
        $table->setPrimaryKey(['id']);

        $this->expectException(Exception\TableExistsException::class);
        $schemaManager->createTable($table);
        $schemaManager->createTable($table);
    }

    public function testNotNullConstraintViolationException(): void
    {
        $schema = new Schema();

        $table = $schema->createTable('notnull_table');
        $table->addColumn('id', 'integer', []);
        $table->addColumn('value', 'integer', ['notnull' => true]);
        $table->setPrimaryKey(['id']);

        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->exec($sql);
        }

        $this->expectException(Exception\NotNullConstraintViolationException::class);
        $this->connection->insert('notnull_table', ['id' => 1, 'value' => null]);
    }

    public function testInvalidFieldNameException(): void
    {
        $schema = new Schema();

        $table = $schema->createTable('bad_fieldname_table');
        $table->addColumn('id', 'integer', []);

        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->exec($sql);
        }

        $this->expectException(Exception\InvalidFieldNameException::class);
        $this->connection->insert('bad_fieldname_table', ['name' => 5]);
    }

    public function testNonUniqueFieldNameException(): void
    {
        $schema = new Schema();

        $table = $schema->createTable('ambiguous_list_table');
        $table->addColumn('id', 'integer');

        $table2 = $schema->createTable('ambiguous_list_table_2');
        $table2->addColumn('id', 'integer');

        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->exec($sql);
        }

        $sql = 'SELECT id FROM ambiguous_list_table, ambiguous_list_table_2';
        $this->expectException(Exception\NonUniqueFieldNameException::class);
        $this->connection->executeQuery($sql);
    }

    public function testUniqueConstraintViolationException(): void
    {
        $schema = new Schema();

        $table = $schema->createTable('unique_field_table');
        $table->addColumn('id', 'integer');
        $table->addUniqueIndex(['id']);

        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->exec($sql);
        }

        $this->connection->insert('unique_field_table', ['id' => 5]);
        $this->expectException(Exception\UniqueConstraintViolationException::class);
        $this->connection->insert('unique_field_table', ['id' => 5]);
    }

    public function testSyntaxErrorException(): void
    {
        $table = new Table('syntax_error_table');
        $table->addColumn('id', 'integer', []);
        $table->setPrimaryKey(['id']);

        $this->connection->getSchemaManager()->createTable($table);

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
        $mode = PHP_OS === 'Linux' ? 0444 : 0000;

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
        $table->addColumn('id', 'integer');

        $this->expectException(Exception\ReadOnlyException::class);
        $this->expectExceptionMessage(
            <<<EOT
An exception occurred while executing "CREATE TABLE no_connection (id INTEGER NOT NULL)":

SQLSTATE[HY000]: General error: 8 attempt to write a readonly database
EOT
        );

        try {
            foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
                $conn->exec($sql);
            }
        } finally {
            $this->cleanupReadOnlyFile($filename);
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @dataProvider getConnectionParams
     */
    public function testConnectionException(array $params): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform) {
            self::markTestSkipped('Only skipped if platform is not sqlite');
        }

        if ($platform instanceof PostgreSQL94Platform && isset($params['password'])) {
            self::markTestSkipped('Does not work on Travis');
        }

        if ($platform instanceof MySqlPlatform && isset($params['user'])) {
            $wrappedConnection = $this->connection->getWrappedConnection();
            assert($wrappedConnection instanceof ServerInfoAwareConnection);

            if (version_compare($wrappedConnection->getServerVersion(), '8', '>=')) {
                self::markTestIncomplete('PHP currently does not completely support MySQL 8');
            }
        }

        $defaultParams = $this->connection->getParams();
        $params        = array_merge($defaultParams, $params);

        $conn = DriverManager::getConnection($params);

        $schema = new Schema();
        $table  = $schema->createTable('no_connection');
        $table->addColumn('id', 'integer');

        $this->expectException(Exception\ConnectionException::class);

        foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
            $conn->exec($sql);
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function getConnectionParams(): iterable
    {
        return [
            [['user' => 'not_existing']],
            [['password' => 'really_not']],
            [['host' => 'localnope']],
        ];
    }

    private function isLinuxRoot(): bool
    {
        return PHP_OS === 'Linux' && posix_getpwuid(posix_geteuid())['name'] === 'root';
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
