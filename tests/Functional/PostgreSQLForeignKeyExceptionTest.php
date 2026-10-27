<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

class PostgreSQLForeignKeyExceptionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('This test requires PostgreSQL.');
        }
    }

    #[DataProvider('referentialActionViolationProvider')]
    public function testReferentialActionViolation(string $action, string $sql): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->executeStatement('CREATE TABLE fk_exception_parent (id INT PRIMARY KEY)');
            $this->connection->executeStatement(sprintf(
                'CREATE TABLE fk_exception_child '
                . '(parent_id INT REFERENCES fk_exception_parent (id) ON DELETE %s ON UPDATE %s)',
                $action,
                $action,
            ));
            $this->connection->executeStatement('INSERT INTO fk_exception_parent VALUES (1)');
            $this->connection->executeStatement('INSERT INTO fk_exception_child VALUES (1)');

            $this->expectException(ForeignKeyConstraintViolationException::class);

            $this->connection->executeStatement($sql);
        } finally {
            $this->connection->rollBack();
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function referentialActionViolationProvider(): iterable
    {
        foreach (['RESTRICT', 'NO ACTION'] as $action) {
            yield $action . ' delete' => [$action, 'DELETE FROM fk_exception_parent WHERE id = 1'];
            yield $action . ' update' => [$action, 'UPDATE fk_exception_parent SET id = 2 WHERE id = 1'];
        }
    }
}
