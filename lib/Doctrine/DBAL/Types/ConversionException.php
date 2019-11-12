<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\DBALException;

/**
 * Conversion Exception is thrown when the database to PHP conversion fails.
 */
class ConversionException extends DBALException
{
}
