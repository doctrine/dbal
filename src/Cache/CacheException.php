<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Exception;

/** @psalm-immutable */
class CacheException extends \Exception implements Exception
{
}
