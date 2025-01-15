<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_intersect_key;
use function array_values;
use function sprintf;

final class ForeignKeyConstraintTest extends FunctionalTestCase
{
    public function testUnnamedForeignKeyConstraint(): void
    {
        $this->dropTableIfExists('users');
        $this->dropTableIfExists('roles');
        $this->dropTableIfExists('teams');

        $roles = new Table('roles');
        $roles->addColumn('id', Types::INTEGER);
        $roles->setPrimaryKey(['id']);

        $teams = new Table('teams');
        $teams->addColumn('id', Types::INTEGER);
        $teams->setPrimaryKey(['id']);

        $users = new Table('users', [
            new Column('id', Type::getType(Types::INTEGER)),
            new Column('role_id', Type::getType(Types::INTEGER)),
            new Column('team_id', Type::getType(Types::INTEGER)),
        ], [], [], [
            new ForeignKeyConstraint(['role_id'], 'roles', ['id']),
            new ForeignKeyConstraint(['team_id'], 'teams', ['id']),
        ]);
        $users->setPrimaryKey(['id']);

        $sm = $this->connection->createSchemaManager();
        $sm->createTable($roles);
        $sm->createTable($teams);
        $sm->createTable($users);

        $table = $sm->introspectTable('users');

        self::assertCount(2, $table->getForeignKeys());
    }

    /**
     * @param array<string,bool> $options
     * @param array<string,bool> $expectedOptions
     *
     * @throws Exception
     */
    #[DataProvider('deferrabilityOptionsProvider')]
    public function testDeferrabilityIntrospection(array $options, array $expectedOptions): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestIncomplete('Not all combinations of options are currently properly introspected on SQLite.');
        } elseif (! $platform instanceof PostgreSQLPlatform) {
            self::markTestSkipped(sprintf(
                'Introspection of constraint deferrability is currently unsupported on %s.',
                $platform::class,
            ));
        }

        $this->dropTableIfExists('users');
        $this->dropTableIfExists('roles');

        $roles = new Table('roles');
        $roles->addColumn('id', Types::INTEGER);
        $roles->setPrimaryKey(['id']);

        $users = new Table('users', [
            new Column('id', Type::getType(Types::INTEGER)),
            new Column('role_id', Type::getType(Types::INTEGER)),
        ], [], [], [
            new ForeignKeyConstraint(['role_id'], 'roles', ['id'], '', $options),
        ]);
        $users->setPrimaryKey(['id']);

        $sm = $this->connection->createSchemaManager();
        $sm->createTable($roles);
        $sm->createTable($users);

        $table = $sm->introspectTable('users');

        /** @var list<ForeignKeyConstraint> $constraints */
        $constraints = array_values($table->getForeignKeys());
        self::assertCount(1, $constraints);
        $constraint = $constraints[0];

        $actualOptions = array_intersect_key($constraint->getOptions(), $expectedOptions);

        self::assertEquals($expectedOptions, $actualOptions);
    }

    /** @return iterable<array{array<string,bool>,array<string,bool>}> */
    public static function deferrabilityOptionsProvider(): iterable
    {
        $notDeferrable = ['deferrable' => false, 'deferred' => false];
        $deferrable    = ['deferrable' => true, 'deferred' => false];
        $deferred      = ['deferrable' => true, 'deferred' => true];

        yield 'unspecified' => [[], $notDeferrable];

        // INITIALLY IMMEDIATE implies NOT DEFERRABLE
        yield 'INITIALLY IMMEDIATE' => [['deferred' => false], $notDeferrable];

        // INITIALLY IMMEDIATE implies DEFERRABLE
        yield 'INITIALLY DEFERRED' => [['deferred' => true], $deferred];

        // NOT DEFERRABLE implies INITIALLY IMMEDIATE
        yield 'NOT DEFERRABLE' => [['deferrable' => false], $notDeferrable];

        yield 'NOT DEFERRABLE INITIALLY IMMEDIATE' => [
            [
                'deferrable' => false,
                'deferred' => false,
            ], $notDeferrable,
        ];

        // DEFERRABLE implies INITIALLY IMMEDIATE
        yield 'DEFERRABLE' => [
            ['deferrable' => true], $deferrable,
        ];

        yield 'DEFERRABLE INITIALLY IMMEDIATE' => [
            [
                'deferrable' => true,
                'deferred' => false,
            ], $deferrable,
        ];

        yield 'DEFERRABLE INITIALLY DEFERRED' => [
            [
                'deferrable' => true,
                'deferred' => true,
            ],
            $deferred,
        ];
    }
}
