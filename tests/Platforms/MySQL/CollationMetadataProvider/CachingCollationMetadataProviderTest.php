<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\MySQL\CollationMetadataProvider;

use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider\CachingCollationMetadataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CachingCollationMetadataProviderTest extends TestCase
{
    #[DataProvider('charsetAndCollationProvider')]
    public function testCharsetCaching(string $collation, ?string $charset): void
    {
        $underlyingProvider = $this->createMock(CollationMetadataProvider::class);
        $underlyingProvider->expects(self::once())
            ->method('getCollationCharset')
            ->with($collation)
            ->willReturn($charset);

        $cachingProvider = new CachingCollationMetadataProvider($underlyingProvider);
        self::assertSame($charset, $cachingProvider->getCollationCharset($collation));
        self::assertSame($charset, $cachingProvider->getCollationCharset($collation));
    }

    /** @return iterable<string,array{string,?string}> */
    public static function charsetAndCollationProvider(): iterable
    {
        yield 'found' => ['utf8mb4_unicode_ci', 'utf8mb4'];
        yield 'not found' => ['utf8mb5_unicode_ci', null];
    }
}
