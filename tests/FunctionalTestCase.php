<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;

abstract class FunctionalTestCase extends TestCase
{
    /**
     * Shared connection when a TestCase is run alone (outside of it's functional suite)
     */
    private static ?Connection $sharedConnection = null;

    protected Connection $connection;

    /**
     * Whether the shared connection could be reused by subsequent tests.
     */
    private bool $isConnectionReusable = true;

    /**
     * Mark shared connection not reusable for subsequent tests.
     *
     * Should be called by the tests that modify configuration
     * or alter the connection state in another way that may impact other tests.
     */
    protected function markConnectionNotReusable(): void
    {
        $this->isConnectionReusable = false;
    }

    #[Before]
    final protected function connect(): void
    {
        if (self::$sharedConnection === null) {
            self::$sharedConnection = TestUtil::getConnection();
        }

        $this->connection = self::$sharedConnection;
    }

    #[After]
    final protected function disconnect(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        if ($this->isConnectionReusable) {
            return;
        }

        if (self::$sharedConnection !== null) {
            self::$sharedConnection->close();
            self::$sharedConnection = null;
        }

        // Make sure the connection is no longer available to the test.
        // Otherwise, there is a chance that a teardown method of the test will reconnect
        // (e.g. to drop a table), and then this reopened connection will remain open and attached to the PHPUnit result
        // until the end of the suite leaking connection resources, while subsequent tests will use
        // the newly established shared connection.
        unset($this->connection);

        $this->isConnectionReusable = true;
    }

    /**
     * Drops the table with the specified name, if it exists.
     *
     * @throws Exception
     */
    public function dropTableIfExists(string $name): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        try {
            $schemaManager->dropTable($name);
        } catch (DatabaseObjectNotFoundException) {
        }
    }

    /**
     * Drops and creates a new table.
     *
     * @throws Exception
     */
    public function dropAndCreateTable(Table $table): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $platform      = $this->connection->getDatabasePlatform();
        $tableName     = $table->getObjectName()->toSQL($platform);

        $this->dropTableIfExists($tableName);
        $schemaManager->createTable($table);
    }

    /**
     * Drops the schema with the specified name, if it exists.
     *
     * @throws Exception
     */
    protected function dropSchemaIfExists(UnqualifiedName $schemaName): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->supportsSchemas()) {
            throw NotSupported::new(__METHOD__);
        }

        $normalizedSchemaName = $schemaName->getIdentifier()
            ->toNormalizedValue($platform);

        $schemaManager  = $this->connection->createSchemaManager();
        $databaseSchema = $schemaManager->introspectSchema();

        $sequencesToDrop = [];
        foreach ($databaseSchema->getSequences() as $sequence) {
            $qualifier = $sequence->getObjectName()
                ->getQualifier();

            if ($qualifier === null || $qualifier->toNormalizedValue($platform) !== $normalizedSchemaName) {
                continue;
            }

            $sequencesToDrop[] = $sequence;
        }

        $tablesToDrop = [];
        foreach ($databaseSchema->getTables() as $table) {
            $qualifier = $table->getObjectName()
                ->getQualifier();

            if ($qualifier === null || $qualifier->toNormalizedValue($platform) !== $normalizedSchemaName) {
                continue;
            }

            $tablesToDrop[] = $table;
        }

        if (count($sequencesToDrop) > 0 || count($tablesToDrop) > 0) {
            $schemaManager->dropSchemaObjects(new Schema($tablesToDrop, $sequencesToDrop));
        }

        try {
            $schemaManager->dropSchema($schemaName->toSQL($platform));
        } catch (DatabaseObjectNotFoundException) {
        }
    }

    /**
     * Drops and creates a new schema.
     *
     * @throws Exception
     */
    protected function dropAndCreateSchema(UnqualifiedName $schemaName): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->supportsSchemas()) {
            throw NotSupported::new(__METHOD__);
        }

        $schemaManager  = $this->connection->createSchemaManager();
        $schemaToCreate = new Schema([], [], null, [$schemaName->toString()]);

        $this->dropSchemaIfExists($schemaName);
        $schemaManager->createSchemaObjects($schemaToCreate);
    }

    /** @throws Exception */
    protected function assertOptionallyQualifiedNameEquals(
        OptionallyQualifiedName $expected,
        OptionallyQualifiedName $actual,
    ): void {
        self::assertEquals(
            $this->toQuotedOptionallyQualifiedName($expected),
            $this->toQuotedOptionallyQualifiedName($actual),
        );
    }

    /** @throws Exception */
    protected function toQuotedOptionallyQualifiedName(OptionallyQualifiedName $name): OptionallyQualifiedName
    {
        return new OptionallyQualifiedName(
            $this->toQuotedIdentifier($name->getUnqualifiedName()),
            $name->getQualifier() === null
                ? null
                : $this->toQuotedIdentifier($name->getQualifier()),
        );
    }

    /** @throws Exception */
    protected function assertUnqualifiedNameEquals(
        UnqualifiedName $expected,
        UnqualifiedName $actual,
    ): void {
        self::assertEquals(
            $this->toQuotedUnqualifiedName($expected),
            $this->toQuotedUnqualifiedName($actual),
        );
    }

    /** @throws Exception */
    protected function toQuotedUnqualifiedName(UnqualifiedName $name): UnqualifiedName
    {
        return new UnqualifiedName(
            $this->toQuotedIdentifier($name->getIdentifier()),
        );
    }

    /**
     * @param non-empty-list<UnqualifiedName> $expected
     * @param non-empty-list<UnqualifiedName> $actual
     *
     * @throws Exception
     */
    protected function assertUnqualifiedNameListEquals(array $expected, array $actual): void
    {
        self::assertEquals(
            $this->toQuotedUnqualifiedNameList($expected),
            $this->toQuotedUnqualifiedNameList($actual),
        );
    }

    /**
     * @param list<UnqualifiedName> $names
     *
     * @return ($names is non-empty-list ? non-empty-list<UnqualifiedName> : list<UnqualifiedName>)
     *
     * @throws Exception
     */
    protected function toQuotedUnqualifiedNameList(array $names): array
    {
        return array_map(
            fn (UnqualifiedName $name): UnqualifiedName => $this->toQuotedUnqualifiedName($name),
            $names,
        );
    }

    /** @throws Exception */
    protected function assertForeignKeyConstraintEquals(
        ForeignKeyConstraint $expected,
        ForeignKeyConstraint $actual,
    ): void {
        self::assertEquals(
            $this->toQuotedForeignKeyConstraint($expected),
            $this->toQuotedForeignKeyConstraint($actual),
        );
    }

    /** @throws Exception */
    protected function toQuotedForeignKeyConstraint(ForeignKeyConstraint $constraint): ForeignKeyConstraint
    {
        $name = $constraint->getObjectName();

        if ($name !== null) {
            $name = $this->toQuotedUnqualifiedName($name);
        }

        return $constraint->edit()
            ->setName($name)
            ->setReferencingColumnNames(...$this->toQuotedUnqualifiedNameList($constraint->getReferencingColumnNames()))
            ->setReferencedTableName($this->toQuotedOptionallyQualifiedName($constraint->getReferencedTableName()))
            ->setReferencedColumnNames(...$this->toQuotedUnqualifiedNameList($constraint->getReferencedColumnNames()))
            ->create();
    }

    /**
     * @param list<ForeignKeyConstraint> $expected
     * @param list<ForeignKeyConstraint> $actual
     *
     * @throws Exception
     */
    protected function assertForeignKeyConstraintListEquals(array $expected, array $actual): void
    {
        self::assertEquals(
            $this->toQuotedForeignKeyConstraintList($expected),
            $this->toQuotedForeignKeyConstraintList($actual),
        );
    }

    /**
     * @param list<ForeignKeyConstraint> $constraints
     *
     * @return ($constraints is non-empty-list ? non-empty-list<ForeignKeyConstraint> : list<ForeignKeyConstraint>)
     *
     * @throws Exception
     */
    protected function toQuotedForeignKeyConstraintList(array $constraints): array
    {
        return array_map(
            fn (ForeignKeyConstraint $constraint): ForeignKeyConstraint => $this->toQuotedForeignKeyConstraint(
                $constraint,
            ),
            $constraints,
        );
    }

    /** @throws Exception */
    protected function toQuotedIdentifier(Identifier $identifier): Identifier
    {
        if ($identifier->isQuoted()) {
            return $identifier;
        }

        return Identifier::quoted(
            $this->connection->getDatabasePlatform()
                ->normalizeUnquotedIdentifier($identifier->getValue()),
        );
    }
}
