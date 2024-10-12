<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\MySQL\CollationMetadataProvider;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider\ConnectionCharsetMetadataProvider;
use PHPUnit\Framework\TestCase;

class ConnectionCharsetMetadataProviderTest extends TestCase
{
    public function testNormalizeCharset(): void
    {
        $connection = $this->createMock(Connection::class);

        $utf8Provider = new ConnectionCharsetMetadataProvider($connection, false);

        self::assertSame('utf8', $utf8Provider->normalizeCharset('utf8'));
        self::assertSame('utf8', $utf8Provider->normalizeCharset('utf8mb3'));
        self::assertSame('foobar', $utf8Provider->normalizeCharset('foobar'));

        $utf8mb3Provider = new ConnectionCharsetMetadataProvider($connection, true);

        self::assertSame('utf8mb3', $utf8mb3Provider->normalizeCharset('utf8'));
        self::assertSame('utf8mb3', $utf8mb3Provider->normalizeCharset('utf8mb3'));
        self::assertSame('foobar', $utf8mb3Provider->normalizeCharset('foobar'));
    }
}
