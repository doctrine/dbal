<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

class Oracle23Platform extends OraclePlatform
{
    public function supportsBulkInserts(): bool
    {
        return true;
    }
}
