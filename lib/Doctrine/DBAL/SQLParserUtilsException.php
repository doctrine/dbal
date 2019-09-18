<?php

namespace Doctrine\DBAL;

use function sprintf;

/**
 * Doctrine\DBAL\ConnectionException
 */
class SQLParserUtilsException extends DBALException
{
    // Exception codes. Dedicated 200-299 numbers
    public const MISSING_PARAMS = 200;
    public const MISSING_TYPE   = 210;

    /**
     * @param string $paramName
     *
     * @return \Doctrine\DBAL\SQLParserUtilsException
     */
    public static function missingParam($paramName)
    {
        return new self(
            sprintf('Value for :%1$s not found in params array. Params array key should be "%1$s"', $paramName),
            self::MISSING_PARAMS
        );
    }

    /**
     * @param string $typeName
     *
     * @return \Doctrine\DBAL\SQLParserUtilsException
     */
    public static function missingType($typeName)
    {
        return new self(
            sprintf('Value for :%1$s not found in types array. Types array key should be "%1$s"', $typeName),
            self::MISSING_TYPE
        );
    }
}
