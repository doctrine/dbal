<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\TestWith;

use function sprintf;

final class SchemaManagerTest extends FunctionalTestCase
{
    use VerifyDeprecations;

    private AbstractSchemaManager $schemaManager;

    /** @throws Exception */
    protected function setUp(): void
    {
        $this->schemaManager = $this->connection->createSchemaManager();
    }

    /** @throws Exception */
    #[TestWith([false])]
    #[TestWith([true])]
    public function testIntrospectTableWithDotInName(bool $quoted): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform->supportsSchemas()) {
            self::markTestIncomplete('DBAL 4.x will fail to introspect this table on a platform that supports schemas');
        }

        $name           = 'example.com';
        $normalizedName = $platform->normalizeUnquotedIdentifier($name);
        $quotedName     = $this->connection->quoteSingleIdentifier($normalizedName);

        // create the table manually since identifiers with dots are not supported in DBAL 4.x
        $sql = sprintf('CREATE TABLE %s (s VARCHAR(16))', $quotedName);

        $this->dropTableIfExists($quotedName);
        $this->connection->executeStatement($sql);

        if ($quoted) {
            $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6768');

            $table = $this->schemaManager->introspectTable($quotedName);
        } else {
            $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6768');

            $table = $this->schemaManager->introspectTable($name);
        }

        self::assertCount(1, $table->getColumns());
    }

    /** @throws Exception */
    public function testIntrospectTableWithInvalidName(): void
    {
        $this->expectException(InvalidName::class);

        $this->schemaManager->introspectTable('"example');
    }
}
