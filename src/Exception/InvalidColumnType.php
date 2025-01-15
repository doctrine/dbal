<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use LogicException;

abstract class InvalidColumnType extends LogicException implements Exception
{
}
