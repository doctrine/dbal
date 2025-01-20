<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\ForeignKeyConstraintEditor;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
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
    /** @throws Exception */
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

    /** @throws Exception */
    public function testColumnIntrospection(): void
    {
        $this->dropTableIfExists('users');
        $this->dropTableIfExists('roles');
        $this->dropTableIfExists('teams');

        $rolesName = OptionallyQualifiedName::unquoted('roles');
        $teamsName = OptionallyQualifiedName::unquoted('teams');

        $roles = new Table($rolesName->toString());
        $roles->addColumn('r_id1', Types::INTEGER);
        $roles->addColumn('r_id2', Types::INTEGER);
        $roles->setPrimaryKey(['r_id1', 'r_id2']);

        $teams = new Table($teamsName->toString());
        $teams->addColumn('t_id1', Types::INTEGER);
        $teams->addColumn('t_id2', Types::INTEGER);
        $teams->setPrimaryKey(['t_id1', 't_id2']);

        $foreignKeyConstraints = [
            ForeignKeyConstraint::editor()
                ->setName(UnqualifiedName::unquoted('fk_roles'))
                ->setReferencingColumnNames(
                    UnqualifiedName::unquoted('role_id1'),
                    UnqualifiedName::unquoted('role_id2'),
                )
                ->setReferencedTableName($roles->getObjectName())
                ->setReferencedColumnNames(
                    UnqualifiedName::unquoted('r_id1'),
                    UnqualifiedName::unquoted('r_id2'),
                )
                ->create(),
            ForeignKeyConstraint::editor()
                ->setName(UnqualifiedName::unquoted('fk_teams'))
                ->setReferencingColumnNames(
                    UnqualifiedName::unquoted('team_id1'),
                    UnqualifiedName::unquoted('team_id2'),
                )
                ->setReferencedTableName($teams->getObjectName())
                ->setReferencedColumnNames(
                    UnqualifiedName::unquoted('t_id1'),
                    UnqualifiedName::unquoted('t_id2'),
                )
                ->create(),
        ];

        $users = new Table('users', [
            new Column('u_id1', Type::getType(Types::INTEGER)),
            new Column('u_id2', Type::getType(Types::INTEGER)),
            new Column('role_id1', Type::getType(Types::INTEGER)),
            new Column('role_id2', Type::getType(Types::INTEGER)),
            new Column('team_id1', Type::getType(Types::INTEGER)),
            new Column('team_id2', Type::getType(Types::INTEGER)),
        ], [], [], $foreignKeyConstraints);
        $users->setPrimaryKey(['u_id1', 'u_id2']);

        $sm = $this->connection->createSchemaManager();
        $sm->createTable($roles);
        $sm->createTable($teams);
        $sm->createTable($users);

        $table = $sm->introspectTable('users');

        $this->assertForeignKeyConstraintListEquals($foreignKeyConstraints, array_values($table->getForeignKeys()));
    }

    /** @throws Exception */
    #[DataProvider('referentialActionProvider')]
    public function testOnUpdateIntrospection(ReferentialAction $action): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $this->platformSupportsOnUpdateAction($platform, $action)) {
            self::markTestSkipped(sprintf(
                '%s does not support ON UPDATE %s',
                $platform::class,
                $action->value,
            ));
        }

        $this->testReferentialActionIntrospection(
            static function (ForeignKeyConstraintEditor $editor, ReferentialAction $action): void {
                $editor->setOnUpdateAction($action);
            },
            $action,
            static fn (ForeignKeyConstraint $constraint): ReferentialAction => $constraint->getOnUpdateAction(),
        );
    }

    /** @throws Exception */
    #[DataProvider('referentialActionProvider')]
    public function testOnDeleteIntrospection(ReferentialAction $action): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $this->platformSupportsOnDeleteAction($platform, $action)) {
            self::markTestSkipped(sprintf(
                '%s does not support ON DELETE %s',
                $platform::class,
                $action->value,
            ));
        }

        $this->testReferentialActionIntrospection(
            static function (ForeignKeyConstraintEditor $editor, ReferentialAction $action): void {
                $editor->setOnDeleteAction($action);
            },
            $action,
            static fn (ForeignKeyConstraint $constraint): ReferentialAction => $constraint->getOnDeleteAction(),
        );
    }

    /**
     * @param callable(ForeignKeyConstraintEditor, ReferentialAction): void $setter
     * @param callable(ForeignKeyConstraint): ReferentialAction             $getter
     *
     * @throws Exception
     */
    private function testReferentialActionIntrospection(
        callable $setter,
        ReferentialAction $action,
        callable $getter,
    ): void {
        $this->dropTableIfExists('users');
        $this->dropTableIfExists('roles');

        $roles = new Table('roles');
        $roles->addColumn('id', Types::INTEGER);
        $roles->setPrimaryKey(['id']);

        $editor = ForeignKeyConstraint::editor()
            ->setReferencingColumnNames(
                UnqualifiedName::unquoted('role_id'),
            )
            ->setReferencedTableName($roles->getObjectName())
            ->setReferencedColumnNames(
                UnqualifiedName::unquoted('id'),
            );
        $setter($editor, $action);

        $users = new Table('users', [
            new Column('id', Type::getType(Types::INTEGER)),
            new Column('role_id', Type::getType(Types::INTEGER), ['notnull' => false]),
        ], [], [], [$editor->create()]);
        $users->setPrimaryKey(['id']);

        $sm = $this->connection->createSchemaManager();

        $sm->createTable($roles);
        $sm->createTable($users);

        $constraints = $sm->listTableForeignKeys('users');

        self::assertCount(1, $constraints);

        $constraint = $constraints[0];

        self::assertSame($action, $getter($constraint));
    }

    /** @return iterable<array{ReferentialAction}> */
    public static function referentialActionProvider(): iterable
    {
        foreach (ReferentialAction::cases() as $referentialAction) {
            yield $referentialAction->value => [$referentialAction];
        }
    }

    private function platformSupportsOnDeleteAction(AbstractPlatform $platform, ReferentialAction $action): bool
    {
        return $this->platformSupportsReferentialAction($platform, $action);
    }

    private function platformSupportsOnUpdateAction(AbstractPlatform $platform, ReferentialAction $action): bool
    {
        if ($platform instanceof OraclePlatform) {
            return false;
        }

        if ($platform instanceof DB2Platform) {
            return match ($action) {
                ReferentialAction::CASCADE,
                ReferentialAction::SET_DEFAULT,
                ReferentialAction::SET_NULL => false,
                default => true,
            };
        }

        return $this->platformSupportsReferentialAction($platform, $action);
    }

    private function platformSupportsReferentialAction(AbstractPlatform $platform, ReferentialAction $action): bool
    {
        if (
            $action === ReferentialAction::RESTRICT
            && ($platform instanceof AbstractMySQLPlatform || $platform instanceof SQLitePlatform)
        ) {
            self::markTestIncomplete(sprintf(
                'Introspection of referential action %s on %s is currently unsupported',
                $action->value,
                $platform::class,
            ));
        }

        if ($platform instanceof SQLServerPlatform) {
            if ($action === ReferentialAction::RESTRICT) {
                return false;
            }
        } elseif ($platform instanceof OraclePlatform) {
            if ($action === ReferentialAction::SET_DEFAULT || $action === ReferentialAction::RESTRICT) {
                return false;
            }
        } elseif ($platform instanceof DB2Platform) {
            if ($action === ReferentialAction::SET_DEFAULT) {
                return false;
            }
        } elseif ($platform instanceof MariaDBPlatform) {
            if (
                $action === ReferentialAction::SET_DEFAULT || $action === ReferentialAction::SET_NULL
                    || $action === ReferentialAction::CASCADE
            ) {
                return false;
            }
        }

        return true;
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
        } elseif (! $platform instanceof PostgreSQLPlatform && ! $platform instanceof OraclePlatform) {
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
