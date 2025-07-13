<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

class BooleanBindingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci', 'oci8')) {
            self::markTestSkipped('Boolean inserts do not work for PDO_OCI and OCI8 as of now');
        }

        $table = new Table('boolean_test_table');
        $table->addColumn('val', 'boolean', ['notnull' => false]);
        $this->dropAndCreateTable($table);
    }

    protected function tearDown(): void
    {
        $this->dropTableIfExists('boolean_test_table');
    }

    /** @dataProvider booleanProvider */
    public function testBooleanInsert(?bool $input): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $result = $queryBuilder->insert('boolean_test_table')->values([
            'val' => $queryBuilder->createNamedParameter($input, ParameterType::BOOLEAN),
        ])->executeStatement();

        self::assertSame(1, $result);

        /** @var boolean|null $valueFromDatabase */
        $valueFromDatabase = $this->connection->createQueryBuilder()
            ->select('val')->from('boolean_test_table')
            ->executeQuery()->fetchOne();
        assert($valueFromDatabase !== false);

        self::assertSame(
            $input,
            $this->connection->convertToPHPValue($valueFromDatabase, 'boolean'),
            'Must return from database the same value inserted.',
        );
    }

    /** @return array<int, list<bool|null>> */
    public static function booleanProvider(): array
    {
        return [[true], [false], [null]];
    }
}
