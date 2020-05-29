<?php

declare(strict_types=1);

namespace Doctrine\DBAL\ForwardCompatibility;

use Doctrine\DBAL\ForwardCompatibility\Driver\ResultStatement as BaseResultStatement;

/**
 * Forward compatibility extension for the DBAL ResultStatement interface.
 *
 * @deprecated
 */
interface ResultStatement extends BaseResultStatement
{
}
