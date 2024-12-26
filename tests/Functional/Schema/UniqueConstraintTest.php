<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

final class UniqueConstraintTest extends FunctionalTestCase
{
    public function testUnnamedUniqueConstraint(): void
    {
        $this->dropTableIfExists('users');

        $users = new Table('users', [
            new Column('id', Type::getType(Types::INTEGER)),
            new Column('username', Type::getType(Types::STRING), ['length' => 32]),
            new Column('email', Type::getType(Types::STRING), ['length' => 255]),
        ], [], [
            new UniqueConstraint('', ['username']),
            new UniqueConstraint('', ['email']),
        ], []);

        $sm = $this->connection->createSchemaManager();
        $sm->createTable($users);

        // we want to assert that the two empty names don't clash, but introspection of unique constraints is currently
        // not supported. for now, we just assert that the table can be created without exceptions.
        $this->expectNotToPerformAssertions();
    }
}
