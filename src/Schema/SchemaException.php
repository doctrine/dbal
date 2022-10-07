<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;

/** @psalm-immutable */
class SchemaException extends \Exception implements Exception
{
}
