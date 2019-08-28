<?php

namespace Doctrine\DBAL\Platforms;

/**
 * Provides behaviour name longer than 32 chars since Oracle 12.2
 *
 */
class Oracle122Platform extends OraclePlatform
{
    /**
     * {@inheritDoc}
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
     */
    public function getMaxIdentifierLength()
    {
        return 128;
    }
}
