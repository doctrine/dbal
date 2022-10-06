<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

use Doctrine\DBAL\Exception;

/** @psalm-immutable */
class QueryException extends \Exception implements Exception
{
}
