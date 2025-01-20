<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\InvalidUniqueConstraintDefinition;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UniqueConstraintTest extends TestCase
{
    use VerifyDeprecations;

    /** @throws Exception */
    public function testGetNonNullObjectName(): void
    {
        $name = UnqualifiedName::unquoted('uq_user_id');

        $uniqueConstraint = UniqueConstraint::editor()
            ->setName($name)
            ->setColumnNames(
                UnqualifiedName::unquoted('user_id'),
            )
            ->create();

        self::assertEquals($name, $uniqueConstraint->getObjectName());
    }

    /** @throws Exception */
    public function testGetNullObjectName(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setColumnNames(
                UnqualifiedName::unquoted('user_id'),
            )
            ->create();

        self::assertNull($uniqueConstraint->getObjectName());
    }

    public function testGetColumnNames(): void
    {
        $columnName = UnqualifiedName::unquoted('user_id');

        $uniqueConstraint = UniqueConstraint::editor()
            ->setColumnNames($columnName)
            ->create();

        self::assertEquals([$columnName], $uniqueConstraint->getColumnNames());
    }

    public function testEmptyColumnNames(): void
    {
        $this->expectException(InvalidUniqueConstraintDefinition::class);

        /** @phpstan-ignore argument.type */
        new UniqueConstraint(null, [], false);
    }

    #[DataProvider('isClusteredProvider')]
    public function testIsClustered(bool $isClustered): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setColumnNames(
                UnqualifiedName::unquoted('user_id'),
            )
            ->setIsClustered($isClustered)
            ->create();

        self::assertSame($isClustered, $uniqueConstraint->isClustered());
    }

    /** @return iterable<array{bool}> $flags */
    public static function isClusteredProvider(): iterable
    {
        yield 'clustered' => [true];
        yield 'not clustered' => [false];
    }
}
