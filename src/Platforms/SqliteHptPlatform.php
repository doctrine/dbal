<?php

namespace Doctrine\DBAL\Platforms;

/**
 * SqliteHptPlatform.
 *
 * An Sqlite platform that supports high precision timestamps.
 */
class SqliteHptPlatform extends SqlitePlatform
{
    /**
     * {@inheritDoc}
     */
    public function getTimeFormatString()
    {
        return 'H:i:s.u';
    }
}
