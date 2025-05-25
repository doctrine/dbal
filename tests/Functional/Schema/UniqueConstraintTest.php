<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

final class UniqueConstraintTest extends FunctionalTestCase
{
    public function testUnnamedUniqueConstraint(): void
    {
        $this->dropTableIfExists('users');

        $users = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('username')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('email')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
            )
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedColumnNames('username')
                    ->create(),
                UniqueConstraint::editor()
                    ->setUnquotedColumnNames('email')
                    ->create(),
            )
            ->create();

        $sm = $this->connection->createSchemaManager();
        $sm->createTable($users);

        // we want to assert that the two empty names don't clash, but introspection of unique constraints is currently
        // not supported. for now, we just assert that the table can be created without exceptions.
        $this->expectNotToPerformAssertions();
    }
}
