<?php

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\DBALException;

/**
 * Exception to be thrown when invalid arguments are passed to any DBAL API
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class InvalidArgumentException extends DBALException
{
    /**
     * @return self
     */
    public static function fromEmptyCriteria()
    {
        return new self('Empty criteria was used, expected non-empty criteria');
    }
}
