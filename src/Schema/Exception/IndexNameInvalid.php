<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use function sprintf;

final class IndexNameInvalid extends InvalidName
{
    public static function new(string $indexName): self
    {
        return new self(sprintf('Invalid index name "%s" given, has to be [a-zA-Z0-9_].', $indexName));
    }
}
