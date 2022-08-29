<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;

/** @internal */
final class ConnectionCollationMetadataProvider implements CollationMetadataProvider
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @throws Exception */
    public function getCollationCharset(string $collation): ?string
    {
        $charset = $this->connection->fetchOne(
            <<<'SQL'
SELECT CHARACTER_SET_NAME
FROM information_schema.COLLATIONS
WHERE COLLATION_NAME = ?;
SQL
            ,
            [$collation],
        );

        if ($charset !== false) {
            return $charset;
        }

        return null;
    }
}
