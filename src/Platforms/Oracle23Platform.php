<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

class Oracle23Platform extends OraclePlatform
{
    /**
     * Oracle 23 supports multiple VALUES rows. No fixed row maximum was found; use the 1,000-row fallback.
     *
     * @see https://docs.oracle.com/en/database/oracle/oracle-database/23/sqlrf/INSERT.html
     */
    public function supportsMultiRowInsert(): bool
    {
        return true;
    }
}
