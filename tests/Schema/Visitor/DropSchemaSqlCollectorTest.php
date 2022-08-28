<?php

namespace Doctrine\DBAL\Tests\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector;
use PHPUnit\Framework\TestCase;

/** @covers \Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector */
class DropSchemaSqlCollectorTest extends TestCase
{
    public function testGetQueriesUsesAcceptedForeignKeys(): void
    {
        $tableOne = $this->createMock(Table::class);
        $tableTwo = $this->createMock(Table::class);

        $keyConstraintOne = $this->getStubKeyConstraint('first');
        $keyConstraintTwo = $this->getStubKeyConstraint('second');

        $platform = $this->getMockBuilder(AbstractPlatform::class)
            ->onlyMethods(['getDropForeignKeySQL'])
            ->getMockForAbstractClass();

        $collector = new DropSchemaSqlCollector($platform);

        $platform->expects(self::exactly(2))
            ->method('getDropForeignKeySQL')
            ->withConsecutive(
                [$keyConstraintOne->getQuotedName($platform), $tableOne->getQuotedName($platform)],
                [$keyConstraintTwo->getQuotedName($platform), $tableTwo->getQuotedName($platform)],
            );

        $collector->acceptForeignKey($tableOne, $keyConstraintOne);
        $collector->acceptForeignKey($tableTwo, $keyConstraintTwo);

        $collector->getQueries();
    }

    private function getStubKeyConstraint(string $name): ForeignKeyConstraint
    {
        $constraint = $this->createMock(ForeignKeyConstraint::class);

        $constraint->expects(self::any())
            ->method('getName')
            ->willReturn($name);

        $constraint->expects(self::any())
            ->method('getForeignColumns')
            ->willReturn([]);

        $constraint->expects(self::any())
            ->method('getColumns')
            ->willReturn([]);

        return $constraint;
    }

    public function testGivenForeignKeyWithZeroLengthAcceptForeignKeyThrowsException(): void
    {
        $collector = new DropSchemaSqlCollector(
            $this->getMockForAbstractClass(AbstractPlatform::class),
        );

        $this->expectException(SchemaException::class);
        $collector->acceptForeignKey(
            $this->createMock(Table::class),
            $this->getStubKeyConstraint(''),
        );
    }
}
