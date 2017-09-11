<?php

namespace Doctrine\DBAL;

use function sprintf;

/**
 * Doctrine\DBAL\ConnectionException
 *
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @link    www.doctrine-project.org
 * @since   2.4
 * @author  Lars Strojny <lars@strojny.net>
 */
class SQLParserUtilsException extends DBALException
{
    /**
     * @param string $paramName
     *
     * @return \Doctrine\DBAL\SQLParserUtilsException
     */
    public static function missingParam($paramName)
    {
        return new self(sprintf('Value for :%1$s not found in params array. Params array key should be "%1$s"', $paramName));
    }

    /**
     * @param string $typeName
     *
     * @return \Doctrine\DBAL\SQLParserUtilsException
     */
    public static function missingType($typeName)
    {
        return new self(sprintf('Value for :%1$s not found in types array. Types array key should be "%1$s"', $typeName));
    }
}
