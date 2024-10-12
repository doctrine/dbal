<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;

/** @internal */
final class ConnectionCharsetMetadataProvider implements CharsetMetadataProvider
{
    public function __construct(private readonly Connection $connection, private bool $useUtf8mb3)
    {
    }

    public function normalizeCharset(string $charset): string
    {
        if ($this->useUtf8mb3 && $charset === 'utf8') {
            return 'utf8mb3';
        }

        if (! $this->useUtf8mb3 && $charset === 'utf8mb3') {
            return 'utf8';
        }

        return $charset;
    }

    /** @throws Exception */
    public function getDefaultCharsetCollation(string $charset): ?string
    {
        $charset = $this->normalizeCharset($charset);

        $collation = $this->connection->fetchOne(
            <<<'SQL'
            SELECT DEFAULT_COLLATE_NAME
            FROM information_schema.CHARACTER_SETS
            WHERE CHARACTER_SET_NAME = ?;
            SQL
            ,
            [$charset],
        );

        if ($collation !== false) {
            return $collation;
        }

        return null;
    }
}
