<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

class BooleanBindingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci', 'oci8')) {
            self::markTestSkipped('Boolean inserts do not work for PDO_OCI and OCI8 as of now');
        }

        $table = Table::editor()
            ->setUnquotedName('boolean_test_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName(Types::BOOLEAN)
                    ->setNotNull(false)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);
    }

    protected function tearDown(): void
    {
        $this->dropTableIfExists('boolean_test_table');
    }

    #[DataProvider('booleanProvider')]
    public function testBooleanParameterInsert(?bool $input): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $result = $queryBuilder->insert('boolean_test_table')->values([
            'val' => $queryBuilder->createNamedParameter($input, ParameterType::BOOLEAN),
        ])->executeStatement();

        self::assertSame(1, $result);

        self::assertSame($input, $this->connection->convertToPHPValue(
            $this->connection->fetchOne('SELECT val FROM boolean_test_table'),
            Types::BOOLEAN,
        ));
    }

    #[DataProvider('booleanProvider')]
    public function testBooleanTypeInsert(?bool $input): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $result = $queryBuilder->insert('boolean_test_table')->values([
            'val' => $queryBuilder->createNamedParameter($input, Types::BOOLEAN),
        ])->executeStatement();

        self::assertSame(1, $result);

        self::assertSame($input, $this->connection->convertToPHPValue(
            $this->connection->fetchOne('SELECT val FROM boolean_test_table'),
            Types::BOOLEAN,
        ));
    }

    /** @return array<string, array{bool|null}> */
    public static function booleanProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false],
            'null' => [null],
        ];
    }
}
