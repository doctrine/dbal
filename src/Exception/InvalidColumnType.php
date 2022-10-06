<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;

/** @psalm-immutable */
abstract class InvalidColumnType extends \Exception implements Exception
{
}
