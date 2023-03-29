<?php

namespace Doctrine\DBAL\Platforms;

/**
 * OracleHptPlatform.
 *
 * An Oracle platform that supports high precision timestamps.
 */
class OracleHptPlatform extends OraclePlatform
{
    /**
     * {@inheritDoc}
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzFormatString()
    {
        return 'Y-m-d H:i:s.u P';
    }
}
