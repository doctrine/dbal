<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;

use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;

use function array_key_exists;

/** @internal */
final class CachingCollationMetadataProvider implements CollationMetadataProvider
{
    /** @var array<string,?string> */
    private array $cache = [];

    public function __construct(private readonly CollationMetadataProvider $collationMetadataProvider)
    {
    }

    public function getCollationCharset(string $collation): ?string
    {
        if (array_key_exists($collation, $this->cache)) {
            return $this->cache[$collation];
        }

        return $this->cache[$collation] = $this->collationMetadataProvider->getCollationCharset($collation);
    }
}
