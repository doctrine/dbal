<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

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
}
