<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;

use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;

use function array_key_exists;

/** @internal */
final class CachingCharsetMetadataProvider implements CharsetMetadataProvider
{
    /** @var array<string,?string> */
    private array $cache = [];

    public function __construct(private readonly CharsetMetadataProvider $charsetMetadataProvider)
    {
    }

    public function getDefaultCharsetCollation(string $charset): ?string
    {
        if (array_key_exists($charset, $this->cache)) {
            return $this->cache[$charset];
        }

        return $this->cache[$charset] = $this->charsetMetadataProvider->getDefaultCharsetCollation($charset);
    }
}
