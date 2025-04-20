<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\TestWith;

final class PrimaryKeyConstraintTest extends FunctionalTestCase
{
    /**
     * The list of tested platforms should be kept in-sync with
     * {@see AbstractPlatformTestCase::testNamedPrimaryKeyConstraintIsReportedAsUnsupported()}.
     */
    public function testNameIntrospection(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform || $platform instanceof SQLitePlatform) {
            self::markTestSkipped(
                'The current database platform does not support named primary key constraints.',
            );
        }

        $primaryKeyConstraint = PrimaryKeyConstraint::editor()
            ->setUnquotedName('users_pk')
            ->setUnquotedColumnNames('id')
            ->create();

        $table = new Table('users');
        $table->addColumn('id', Types::INTEGER);
        $table->addPrimaryKeyConstraint($primaryKeyConstraint);

        $this->dropAndCreateTable($table);

        $sm = $this->connection->createSchemaManager();

        $table = $sm->introspectTable('users');

        $this->assertPrimaryKeyConstraintEquals($primaryKeyConstraint, $table->getPrimaryKeyConstraint());
    }

    /**
     * The list of tested platforms should be kept in-sync with
     * {@see AbstractPlatformTestCase::testNonClusteredPrimaryKeyConstraintIsReportedAsUnsupported()}.
     */
    #[TestWith([false])]
    #[TestWith([true])]
    public function testIsClusteredIntrospection(bool $isClustered): void
    {
        if (! $isClustered && ! $this->connection->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::markTestSkipped(
                'The current database platform does not support non-clustered primary key constraints.',
            );
        }

        $primaryKeyConstraint = PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames('id')
            ->setIsClustered($isClustered)
            ->create();

        $table = new Table('users');
        $table->addColumn('id', Types::INTEGER);
        $table->addPrimaryKeyConstraint($primaryKeyConstraint);

        $this->dropAndCreateTable($table);

        $sm = $this->connection->createSchemaManager();

        $table = $sm->introspectTable('users');

        $this->assertPrimaryKeyConstraintEquals($primaryKeyConstraint, $table->getPrimaryKeyConstraint());
    }
}
