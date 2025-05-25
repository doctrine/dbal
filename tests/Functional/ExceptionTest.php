<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_merge;
use function chmod;
use function exec;
use function extension_loaded;
use function file_exists;
use function func_get_args;
use function posix_geteuid;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function sys_get_temp_dir;
use function touch;
use function unlink;

use const E_WARNING;
use const PHP_OS_FAMILY;

/** @phpstan-import-type Params from DriverManager */
class ExceptionTest extends FunctionalTestCase
{
    public function testPrimaryConstraintViolationException(): void
    {
        $table = Table::editor()
            ->setUnquotedName('duplicatekey_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

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
        $schemaManager = $this->connection->createSchemaManager();

        $table = Table::editor()
            ->setUnquotedName('alreadyexist_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectException(Exception\TableExistsException::class);
        $schemaManager->createTable($table);
        $schemaManager->createTable($table);
    }

    public function testNotNullConstraintViolationException(): void
    {
        $table = Table::editor()
            ->setUnquotedName('notnull_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->expectException(Exception\NotNullConstraintViolationException::class);
        $this->connection->insert('notnull_table', ['id' => 1, 'val' => null]);
    }

    public function testInvalidFieldNameException(): void
    {
        $table = Table::editor()
            ->setUnquotedName('bad_columnname_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->expectException(Exception\InvalidFieldNameException::class);

        // prevent the PHPUnit error handler from handling the warning that db2_bind_param() may trigger
        /** @var callable|null $previous */
        $previous = null;
        $previous = set_error_handler(static function (int $errno) use (&$previous): bool {
            if (($errno & ~E_WARNING) === 0) {
                return true;
            }

            return $previous !== null && $previous(...func_get_args());
        });

        try {
            $this->connection->insert('bad_columnname_table', ['name' => 5]);
        } finally {
            restore_error_handler();
        }
    }

    public function testNonUniqueFieldNameException(): void
    {
        $table1 = Table::editor()
            ->setUnquotedName('ambiguous_list_table_1')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table1);

        $table2 = Table::editor()
            ->setUnquotedName('ambiguous_list_table_2')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table2);

        $sql = 'SELECT id FROM ambiguous_list_table_1, ambiguous_list_table_2';
        $this->expectException(Exception\NonUniqueFieldNameException::class);
        $this->connection->executeQuery($sql);
    }

    public function testUniqueConstraintViolationException(): void
    {
        $table = Table::editor()
            ->setUnquotedName('unique_column_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table->addUniqueIndex(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('unique_column_table', ['id' => 5]);
        $this->expectException(Exception\UniqueConstraintViolationException::class);
        $this->connection->insert('unique_column_table', ['id' => 5]);
    }

    public function testSyntaxErrorException(): void
    {
        $table = Table::editor()
            ->setUnquotedName('syntax_error_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $sql = 'SELECT id FRO syntax_error_table';
        $this->expectException(Exception\SyntaxErrorException::class);
        $this->connection->executeQuery($sql);
    }

    public function testConnectionExceptionSqLite(): void
    {
        if (! ($this->connection->getDatabasePlatform() instanceof SQLitePlatform)) {
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

        if ($this->isPosixSuperUser()) {
            exec(sprintf('chattr +i %s', $filename));
        }

        $params = [
            'driver' => 'pdo_sqlite',
            'path'   => $filename,
        ];
        $conn   = DriverManager::getConnection($params);

        $table = Table::editor()
            ->setUnquotedName('no_connection')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $schemaManager = $conn->createSchemaManager();

        $this->expectException(Exception\ReadOnlyException::class);
        $this->expectExceptionMessage(
            'An exception occurred while executing a query: SQLSTATE[HY000]: ' .
            'General error: 8 attempt to write a readonly database',
        );

        try {
            $schemaManager->createTable($table);
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
     * @phpstan-param Params $params
     */
    #[DataProvider('getConnectionParams')]
    private function testConnectionException(array $params): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped('The SQLite driver does not use a network connection');
        }

        $params = array_merge(TestUtil::getConnectionParams(), $params);
        $conn   = DriverManager::getConnection($params);

        $this->expectException(Exception\ConnectionException::class);
        $conn->executeQuery($platform->getDummySelectSQL());
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

    private function isPosixSuperUser(): bool
    {
        return extension_loaded('posix') && posix_geteuid() === 0;
    }

    private function cleanupReadOnlyFile(string $filename): void
    {
        if ($this->isPosixSuperUser()) {
            exec(sprintf('chattr -i %s', $filename));
        }

        chmod($filename, 0200); // make the file writable again, so it can be removed on Windows
        unlink($filename);
    }
}
