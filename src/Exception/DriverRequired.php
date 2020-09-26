<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;

use function sprintf;

/**
 * @psalm-immutable
 */
final class DriverRequired extends Exception
{
    /**
     * @param string|null $url The URL that was provided in the connection parameters (if any).
     */
    public static function new(?string $url = null): self
    {
        if ($url !== null) {
            return new self(
                sprintf(
                    'The options "driver" or "driverClass" are mandatory if a connection URL without scheme '
                        . 'is given to DriverManager::getConnection(). Given URL "%s".',
                    $url
                )
            );
        }

        return new self(
            'The options "driver" or "driverClass" are mandatory if no PDO '
                . 'instance is given to DriverManager::getConnection().'
        );
    }
}
