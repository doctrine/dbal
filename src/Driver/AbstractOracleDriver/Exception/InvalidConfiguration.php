<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\AbstractOracleDriver\Exception;

use Doctrine\DBAL\Driver\AbstractException;

/** @internal */
final class InvalidConfiguration extends AbstractException
{
    public static function fromMissingHostAndConnectString(): self
    {
        return new self('Neither of the "host" and "connectstring" parameters is specified.');
    }
}
