<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use PHPUnit\Framework\Attributes\DataProvider;

class BooleanBindingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci', 'oci8')) {
            self::markTestSkipped('Boolean inserts do not work for PDO_OCI and OCI8 as of now');
        }

        $table = new Table('boolean_test_table');
        $table->addColumn('val', 'boolean');
        $this->dropAndCreateTable($table);
    }

    protected function tearDown(): void
    {
        $this->dropTableIfExists('boolean_test_table');
    }

    #[DataProvider('booleanProvider')]
    public function testBooleanInsert(bool $input): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $result = $queryBuilder->insert('boolean_test_table')->values([
            'val' => $queryBuilder->createNamedParameter($input, ParameterType::BOOLEAN),
        ])->executeStatement();

        self::assertSame(1, $result);
    }

    /** @return bool[][] */
    public static function booleanProvider(): array
    {
        return [[true], [false]];
    }
}
