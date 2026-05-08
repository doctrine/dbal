<?php

declare(strict_types=1);

namespace Doctrine\DBAL\SQL\Parser;

use Throwable;

/**
 * @deprecated The SQL parser does not raise checked exceptions. The only concrete
 *             implementation, {@see Exception\RegularExpressionError}, is an unchecked
 *             {@see \RuntimeException} subclass. This interface will be removed in 5.0.
 */
interface Exception extends Throwable
{
}
