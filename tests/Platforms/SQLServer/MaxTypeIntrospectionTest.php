<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\SQLServer;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

/**
 * SQL Server reports TEXT, NTEXT and their modern NVARCHAR(MAX)/VARCHAR(MAX)/VARBINARY(MAX)
 * equivalents with a character or byte length of -1. The metadata provider normalises those
 * back to the large-object types, so a MAX column introspects as text/blob rather than as a
 * string with a nonsense length.
 *
 * @see \Doctrine\DBAL\Platforms\SQLServer\SQLServerMetadataProvider
 */
final class MaxTypeIntrospectionTest extends TestCase
{
    public function testTextTypeMapsToText(): void
    {
        $platform = new SQLServerPlatform();

        self::assertSame(Types::TEXT, $platform->getDoctrineTypeMapping('text'));
    }

    public function testLegacyNtextMapsToText(): void
    {
        $platform = new SQLServerPlatform();

        // NTEXT is the pre-NVARCHAR(MAX) equivalent, so it is the reference for what a
        // MAX unicode column should introspect to.
        self::assertSame(Types::TEXT, $platform->getDoctrineTypeMapping('ntext'));
    }

    public function testNvarcharMapsToStringWhenLengthIsBounded(): void
    {
        $platform = new SQLServerPlatform();

        // A bounded NVARCHAR stays a string; only the MAX form is promoted to a LOB.
        self::assertSame(Types::STRING, $platform->getDoctrineTypeMapping('nvarchar'));
        self::assertSame(
            'NVARCHAR(50)',
            $platform->getStringTypeDeclarationSQL(['length' => 50]),
        );
    }

    public function testMaxNvarcharDeclarationRoundTripsThroughClob(): void
    {
        $platform = new SQLServerPlatform();

        // getClobTypeDeclarationSQL is what a text column is written back out as, so a
        // MAX column introspected as text must be writable back as VARCHAR(MAX).
        self::assertSame('VARCHAR(MAX)', $platform->getClobTypeDeclarationSQL([]));
    }

    public function testVarbinaryMapsToBlobWhenMax(): void
    {
        $platform = new SQLServerPlatform();

        self::assertSame(Types::BLOB, $platform->getDoctrineTypeMapping('blob'));
    }

    public function testClobAndBlobDeclareAsMaxTypes(): void
    {
        $platform = new SQLServerPlatform();

        // The platform's LOB declarations are what a MAX column is written back out
        // as, so a column introspected as text must round-trip to VARCHAR(MAX).
        self::assertSame('VARCHAR(MAX)', $platform->getClobTypeDeclarationSQL([]));
        self::assertSame('VARBINARY(MAX)', $platform->getBlobTypeDeclarationSQL([]));
    }
}
