<?php

namespace Doctrine\DBAL\Platforms;

use function strlen;
use function substr;

/**
 * Provides behaviour name longer than 32 chars since Oracle 12.2
 */
class Oracle122Platform extends OraclePlatform
{
    /**
     * {@inheritDoc}
     *
     * @param string $schemaElementName
     *
     * @return string
     */
    public function fixSchemaElementName($schemaElementName)
    {
        if (strlen($schemaElementName) > 128) {
            // Trim it
            return substr($schemaElementName, 0, 128);
        }

        return $schemaElementName;
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public function getMaxIdentifierLength()
    {
        return 128;
    }
}
