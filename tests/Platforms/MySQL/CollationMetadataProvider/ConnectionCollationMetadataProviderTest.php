<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\MySQL\CollationMetadataProvider;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider\ConnectionCollationMetadataProvider;
use PHPUnit\Framework\TestCase;

class ConnectionCollationMetadataProviderTest extends TestCase
{
    public function testNormalizeCcollation(): void
    {
        $connection = $this->createMock(Connection::class);

        $utf8Provider = new ConnectionCollationMetadataProvider($connection, false);

        self::assertSame('utf8_unicode_ci', $utf8Provider->normalizeCollation('utf8_unicode_ci'));
        self::assertSame('utf8_unicode_ci', $utf8Provider->normalizeCollation('utf8mb3_unicode_ci'));
        self::assertSame('foobar_unicode_ci', $utf8Provider->normalizeCollation('foobar_unicode_ci'));

        $utf8mb3Provider = new ConnectionCollationMetadataProvider($connection, true);

        self::assertSame('utf8mb3_unicode_ci', $utf8mb3Provider->normalizeCollation('utf8_unicode_ci'));
        self::assertSame('utf8mb3_unicode_ci', $utf8mb3Provider->normalizeCollation('utf8mb3_unicode_ci'));
        self::assertSame('foobar_unicode_ci', $utf8mb3Provider->normalizeCollation('foobar_unicode_ci'));
    }
}
