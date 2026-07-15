<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Introspection;

use Doctrine\DBAL\Schema\Introspection\IntrospectingSchemaProvider;
use Doctrine\DBAL\Schema\Metadata\MetadataProvider;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

class IntrospectingSchemaProviderTest extends TestCase
{
    use VerifyDeprecations;

    public function testGetUniqueConstraintsForTableWithoutMetadataSupport(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7461');

        self::assertSame(
            [],
            $this->createProvider()->getUniqueConstraintsForTable(null, 'orders'),
        );
    }

    public function testGetAllTablesWithoutUniqueConstraintMetadataSupport(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7461');

        self::assertSame([], $this->createProvider()->getAllTables());
    }

    private function createProvider(): IntrospectingSchemaProvider
    {
        return new IntrospectingSchemaProvider(
            self::createStub(MetadataProvider::class),
            null,
            (new SchemaConfig())->toTableConfiguration(),
        );
    }
}
