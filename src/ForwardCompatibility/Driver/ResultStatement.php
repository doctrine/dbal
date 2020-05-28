<?php

declare(strict_types=1);

namespace Doctrine\DBAL\ForwardCompatibility\Driver;

use Doctrine\DBAL\Driver\ResultStatement as BaseResultStatement;

/**
 * Forward compatibility extension for the ResultStatement interface.
 *
 * @deprecated
 */
interface ResultStatement extends BaseResultStatement
{
}
