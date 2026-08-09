<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;

class Oracle23Platform extends OraclePlatform
{
    public function supportsBulkInserts(): bool
    {
        return true;
    }
}
